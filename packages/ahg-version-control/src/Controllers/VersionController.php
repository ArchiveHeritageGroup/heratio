<?php

/**
 * VersionController — list + show + diff actions for the version-history UI.
 *
 * Routes:
 *   GET  /version-control/{entity}/{id}             → list
 *   GET  /version-control/{entity}/{id}/{number}    → show
 *   GET  /version-control/{entity}/{id}/diff/{v1}/{v2} → diff (Phase G renders the UI)
 *
 * @phase F (list + show) — diff scaffolded for Phase G
 */

namespace AhgVersionControl\Controllers;

use AhgVersionControl\Services\AclCheck;
use AhgVersionControl\Services\DiffComputer;
use AhgVersionControl\Services\InsufficientClearanceException;
use AhgVersionControl\Services\RestoreService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class VersionController extends Controller
{
    private const ENTITY_TABLE_MAP = [
        'information_object' => [
            'version_table' => 'information_object_version',
            'fk'            => 'information_object_id',
            'parent_table'  => 'information_object',
            'i18n_table'    => 'information_object_i18n',
            'title_field'   => 'title',
        ],
        'actor' => [
            'version_table' => 'actor_version',
            'fk'            => 'actor_id',
            'parent_table'  => 'actor',
            'i18n_table'    => 'actor_i18n',
            'title_field'   => 'authorized_form_of_name',
        ],
    ];

    private const PAGE_SIZE = 20;

    public function list(string $entity, int $id, Request $request)
    {
        $this->assertEntity($entity, $id);
        $this->requireAclAction(AclCheck::ACTION_LIST);
        $config = self::ENTITY_TABLE_MAP[$entity];

        $page = max(1, (int) $request->query('page', 1));
        $total = (int) DB::table($config['version_table'])->where($config['fk'], $id)->count();

        $versions = DB::table($config['version_table'])
            ->leftJoin('user', 'user.id', '=', $config['version_table'] . '.created_by')
            ->where($config['fk'], $id)
            ->orderBy('version_number', 'desc')
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->select(
                $config['version_table'] . '.id',
                $config['version_table'] . '.version_number',
                $config['version_table'] . '.change_summary',
                $config['version_table'] . '.changed_fields',
                $config['version_table'] . '.created_by',
                $config['version_table'] . '.created_at',
                $config['version_table'] . '.is_restore',
                $config['version_table'] . '.restored_from_version',
                'user.username AS created_by_username',
            )
            ->get();

        return view('ahg-version-control::list', [
            'entityType'  => $entity,
            'entityId'    => $id,
            'entityTitle' => $this->resolveTitle($entity, $id),
            'entitySlug'  => $this->resolveSlug($id),
            'versions'    => $versions,
            'totalCount'  => $total,
            'page'        => $page,
            'pageSize'    => self::PAGE_SIZE,
            'totalPages'  => max(1, (int) ceil($total / self::PAGE_SIZE)),
        ]);
    }

    public function show(string $entity, int $id, int $number)
    {
        $this->assertEntity($entity, $id);
        $this->requireAclAction(AclCheck::ACTION_LIST);
        $config = self::ENTITY_TABLE_MAP[$entity];

        $row = DB::table($config['version_table'])
            ->leftJoin('user', 'user.id', '=', $config['version_table'] . '.created_by')
            ->where($config['fk'], $id)
            ->where('version_number', $number)
            ->select($config['version_table'] . '.*', 'user.username AS created_by_username')
            ->first();

        if (!$row) {
            abort(404);
        }

        return view('ahg-version-control::show', [
            'entityType'    => $entity,
            'entityId'      => $id,
            'versionNumber' => $number,
            'version'       => $row,
            'snapshot'      => is_string($row->snapshot) ? (json_decode($row->snapshot, true) ?? []) : [],
            'changedFields' => is_string($row->changed_fields) ? (json_decode($row->changed_fields, true) ?? []) : [],
            'entityTitle'   => $this->resolveTitle($entity, $id),
            'entitySlug'    => $this->resolveSlug($id),
        ]);
    }

    public function restore(string $entity, int $id, int $number, Request $request, RestoreService $service)
    {
        $this->assertEntity($entity, $id);

        // Always require version.restore.
        $this->requireAclAction(AclCheck::ACTION_RESTORE);

        // Additionally require version.restore_classified when the target
        // record is classified. The Phase J clearance check still runs inside
        // RestoreService::restore() for the actual level comparison.
        $isClassified = DB::table('object_security_classification')
            ->where('object_id', $id)->where('active', 1)->exists();
        if ($isClassified) {
            $this->requireAclAction(AclCheck::ACTION_RESTORE_CLASSIFIED);
        }

        try {
            $newVersion = $service->restore(
                entityType: $entity,
                entityId: $id,
                targetVersionNumber: $number,
                userId: (int) auth()->id() ?: null,
            );
            return redirect()->route('version-control.list', ['entity' => $entity, 'id' => $id])
                ->with('notice', sprintf(__('Restored from v%1$d. New version v%2$d created.'), $number, $newVersion));
        } catch (InsufficientClearanceException $e) {
            abort(403, $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('version-control.list', ['entity' => $entity, 'id' => $id])
                ->with('error', __('Restore failed: ') . $e->getMessage());
        }
    }

    /**
     * Phase K — gate the action on a version.* ACL permission. 403 on failure.
     */
    private function requireAclAction(string $action): void
    {
        $userId = (int) (auth()->id() ?? 0) ?: null;
        $check = new AclCheck();
        if (!$check->canUserDo($userId, $action)) {
            abort(403);
        }
    }

    public function diff(string $entity, int $id, int $v1, int $v2, DiffComputer $computer)
    {
        $this->assertEntity($entity, $id);
        $this->requireAclAction(AclCheck::ACTION_DIFF);
        $config = self::ENTITY_TABLE_MAP[$entity];

        $snap1 = DB::table($config['version_table'])->where($config['fk'], $id)->where('version_number', $v1)->value('snapshot');
        $snap2 = DB::table($config['version_table'])->where($config['fk'], $id)->where('version_number', $v2)->value('snapshot');
        if (!is_string($snap1) || !is_string($snap2)) {
            abort(404);
        }

        $diff = $computer->diff(json_decode($snap1, true) ?? [], json_decode($snap2, true) ?? []);

        return view('ahg-version-control::diff', [
            'entityType'  => $entity,
            'entityId'    => $id,
            'entityTitle' => $this->resolveTitle($entity, $id),
            'v1'          => $v1,
            'v2'          => $v2,
            'diff'        => $diff,
        ]);
    }

    private function assertEntity(string $entity, int $id): void
    {
        if (!isset(self::ENTITY_TABLE_MAP[$entity]) || $id <= 0) {
            abort(404);
        }
    }

    private function resolveTitle(string $entity, int $id): string
    {
        $config = self::ENTITY_TABLE_MAP[$entity];
        $culture = app()->getLocale();
        $title = DB::table($config['i18n_table'])
            ->where('id', $id)
            ->where('culture', $culture)
            ->value($config['title_field']);
        if (is_string($title) && $title !== '') {
            return $title;
        }
        $title = DB::table($config['i18n_table'])
            ->where('id', $id)
            ->orderBy('culture')
            ->value($config['title_field']);
        return is_string($title) && $title !== '' ? $title : "#{$id}";
    }

    private function resolveSlug(int $objectId): ?string
    {
        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        return is_string($slug) ? $slug : null;
    }
}
