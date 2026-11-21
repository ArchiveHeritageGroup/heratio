<?php

/**
 * TreeMoveService - move a description to a new parent and/or position (heratio#1582).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgInformationObjectManage\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\HierarchyQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tree's drag-and-drop and its keyboard "Move..." both land here.
 *
 * A parent change goes through InformationObjectService::moveUnder(), the
 * same move the Move page and the dedupe merge use, so the nested set, the
 * closure tree, sibling order, the audit entry and the ES ancestors stay
 * right. The position among the new siblings is then set with the sibling
 * reorder (TreeviewService::moveBefore/moveAfter) where every sibling has
 * nested-set bounds, else by renumbering the sibling order alone.
 *
 * Every result carries the old parent and position, so the caller can offer
 * undo by moving the record back.
 */
class TreeMoveService
{
    public const ROOT_ID = 1;

    private const ENTITY = 'information_object';

    /**
     * @return array{ok:bool, status:int, error?:string, code?:string, ...}
     */
    public function move(int $id, int $newParentId, ?int $position, bool $confirmed, ?int $userId): array
    {
        $node = DB::table('information_object')->where('id', $id)->first(['id', 'parent_id']);
        if (! $node || $id === self::ROOT_ID) {
            return $this->fail(404, 'not_found', __('That description was not found.'));
        }
        if ($newParentId !== self::ROOT_ID && ! DB::table('information_object')->where('id', $newParentId)->exists()) {
            return $this->fail(404, 'not_found', __('The new parent was not found.'));
        }
        if ($this->hierarchy()->isWithin(self::ENTITY, $newParentId, $id)) {
            return $this->fail(422, 'descendant', __('A description cannot be moved under itself or one of its own descendants.'));
        }

        $oldParentId = (int) $node->parent_id;
        foreach (array_unique([$id, $oldParentId, $newParentId]) as $check) {
            // The root is not a record: moving to or from the top level needs the global grant.
            $allowed = $check === self::ROOT_ID
                ? AclService::hasPermission($userId, 'update')
                : AclService::hasPermission($userId, 'update', $check);
            if (! $allowed) {
                return $this->fail(403, 'forbidden', __('You do not have permission to move this description there.'));
            }
        }
        if (\AhgCore\Http\Middleware\DeclaredRecordLock::isDeclared($id)) {
            return $this->fail(423, 'locked', __('This is a declared record: its description and files are read-only.'));
        }

        $oldFonds = $this->fondsOf($id);
        $newFonds = $newParentId === self::ROOT_ID ? $id : $this->fondsOf($newParentId);
        if ($oldFonds !== $newFonds && ! $confirmed) {
            return $this->fail(409, 'confirm', __('This moves the description out of ":from" into ":to". Move it anyway?', [
                'from' => $this->titleOf($oldFonds), 'to' => $newParentId === self::ROOT_ID ? __('the top level') : $this->titleOf($newFonds),
            ])) + ['from_fonds' => $oldFonds, 'to_fonds' => $newFonds];
        }

        $oldSiblings = $this->childIds($oldParentId);
        $oldPosition = (int) array_search($id, $oldSiblings, true);

        DB::transaction(function () use ($id, $oldParentId, $newParentId, $position) {
            if ($oldParentId !== $newParentId) {
                InformationObjectService::moveUnder($id, $newParentId);
            }
            $this->placeAt($id, $newParentId, $position);
        });

        $newPosition = (int) array_search($id, $this->childIds($newParentId), true);

        return [
            'ok' => true,
            'status' => 200,
            'id' => $id,
            'old_parent_id' => $oldParentId,
            'old_position' => $oldPosition,
            'new_parent_id' => $newParentId,
            'new_position' => $newPosition,
            'cross_fonds' => $oldFonds !== $newFonds,
            // Undo is the reverse move; confirmed, since the user already agreed to the first.
            'undo' => ['id' => $id, 'new_parent_id' => $oldParentId, 'position' => $oldPosition, 'confirm' => 1],
        ];
    }

    /**
     * A parent's children in sibling order (lft where a child has no order row).
     *
     * @return list<int>
     */
    public function childIds(int $parentId): array
    {
        $q = DB::table('information_object as io')->where('io.parent_id', $parentId)->select('io.id');
        $this->hierarchy()->applySiblingOrder($q, self::ENTITY, 'io.id', 'io.lft');

        return $q->orderBy('io.id')->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** The top-level description (fonds) a record sits in; a top-level record is its own. */
    public function fondsOf(int $id): int
    {
        $ancestors = array_values(array_filter(
            $this->hierarchy()->ancestorIds(self::ENTITY, $id),
            fn ($a) => $a !== self::ROOT_ID
        ));

        return $ancestors[0] ?? $id;
    }

    /**
     * Parents to offer in the Move picker: title or identifier match, records
     * the user may update, never the moving record or anything inside it.
     *
     * @return list<array{id:int, title:string, identifier:?string, fonds:?string}>
     */
    public function searchTargets(string $q, int $movingId, ?int $userId, int $limit = 20): array
    {
        $culture = app()->getLocale();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $rows = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as c', function ($j) use ($culture) {
                $j->on('c.id', '=', 'io.id')->where('c.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as s', function ($j) {
                $j->on('s.id', '=', 'io.id')->on('s.culture', '=', 'io.source_culture');
            })
            ->where('io.id', '>', self::ROOT_ID)
            ->where(function ($w) use ($like) {
                $w->where('c.title', 'like', $like)->orWhere('s.title', 'like', $like)->orWhere('io.identifier', 'like', $like);
            })
            ->orderByRaw('COALESCE(c.title, s.title)')
            ->limit($limit * 3)
            ->get(['io.id', 'io.identifier', DB::raw('COALESCE(c.title, s.title) as title')]);

        $out = [];
        foreach ($rows as $r) {
            $rid = (int) $r->id;
            if ($movingId && $this->hierarchy()->isWithin(self::ENTITY, $rid, $movingId)) {
                continue;
            }
            if (! AclService::hasPermission($userId, 'update', $rid)) {
                continue;
            }
            $fonds = $this->fondsOf($rid);
            $out[] = [
                'id' => $rid,
                'title' => (string) ($r->title ?: __('Untitled')),
                'identifier' => $r->identifier ?: null,
                'fonds' => $fonds !== $rid ? $this->titleOf($fonds) : null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Children of a parent for the position choice, in sibling order.
     *
     * @return list<array{id:int, title:string, identifier:?string}>
     */
    public function childOptions(int $parentId, int $limit = 200): array
    {
        $ids = array_slice($this->childIds($parentId), 0, $limit);
        if (! $ids) {
            return [];
        }
        $titles = $this->titles($ids);

        return array_map(fn ($cid) => [
            'id' => $cid,
            'title' => $titles[$cid]->title ?? __('Untitled'),
            'identifier' => $titles[$cid]->identifier ?? null,
        ], $ids);
    }

    /**
     * Put $id at index $position among its parent's children (null = last).
     */
    private function placeAt(int $id, int $parentId, ?int $position): void
    {
        $others = array_values(array_diff($this->childIds($parentId), [$id]));
        $position = $position === null ? count($others) : max(0, min($position, count($others)));
        if (! $others) {
            return;
        }
        if (array_search($id, $this->childIds($parentId), true) === $position) {
            return;
        }

        // Every sibling with nested-set bounds: the sibling reorder keeps lft
        // and sibling order in step. moveSibling() cannot place a record that
        // has no bounds, so a mixed list is ordered by sibling order alone.
        $unbounded = DB::table('information_object')->where('parent_id', $parentId)
            ->where(fn ($w) => $w->whereNull('lft')->orWhereNull('rgt')->orWhere('lft', '<=', 0))->exists();
        if (! $unbounded) {
            $tv = new TreeviewService();
            $done = $position === 0 ? $tv->moveBefore($id, $others[0]) : $tv->moveAfter($id, $others[$position - 1]);
            if ($done) {
                return;
            }
        }

        if (! Schema::hasTable('ahg_node_sibling_order')) {
            return;
        }
        array_splice($others, $position, 0, [$id]);
        foreach ($others as $order => $cid) {
            DB::table('ahg_node_sibling_order')->updateOrInsert(
                ['entity' => self::ENTITY, 'node_id' => $cid],
                ['parent_id' => $parentId, 'sibling_order' => $order]
            );
        }
    }

    private function titleOf(int $id): string
    {
        $t = $this->titles([$id])[$id] ?? null;

        return (string) (($t->title ?? null) ?: (($t->identifier ?? null) ?: __('Untitled')));
    }

    /** @return array<int, object{title:?string, identifier:?string}> */
    private function titles(array $ids): array
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as c', function ($j) use ($culture) {
                $j->on('c.id', '=', 'io.id')->where('c.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as s', function ($j) {
                $j->on('s.id', '=', 'io.id')->on('s.culture', '=', 'io.source_culture');
            })
            ->whereIn('io.id', $ids)
            ->get(['io.id', 'io.identifier', DB::raw('COALESCE(c.title, s.title) as title')])
            ->keyBy(fn ($r) => (int) $r->id)
            ->all();
    }

    private function fail(int $status, string $code, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'code' => $code, 'error' => $error];
    }

    private function hierarchy(): HierarchyQueryService
    {
        return app(HierarchyQueryService::class);
    }
}
