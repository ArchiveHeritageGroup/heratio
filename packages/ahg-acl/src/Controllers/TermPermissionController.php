<?php

/**
 * TermPermissionController - Controller for Heratio
 *
 * Issue #744 - Admin term ACL matrix page.
 *
 * PSIS parity surface: /admin/termPermission. PSIS only ever exposed a static
 * "term is locked" placeholder; Heratio implements the real matrix the page
 * was meant to be (rows = ACL groups, columns = taxonomy permissions:
 * create / view / update / delete). Updates POST as AJAX and write straight
 * into acl_permission.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgAcl\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermPermissionController extends Controller
{
    /**
     * Term-permission actions exposed by the matrix. The four CRUD-ish
     * actions match AtoM's qbAclPlugin term ACL surface (create / view /
     * update / delete) - "view" is rendered as "read" in acl_permission
     * for backwards compatibility with the existing AclService::TERM_ACTIONS.
     */
    public const ACTIONS = [
        'create' => 'Create',
        'read' => 'View',
        'update' => 'Update',
        'delete' => 'Delete',
    ];

    /**
     * GET /admin/term-permissions - render the matrix.
     */
    public function index(Request $request)
    {
        $groups = $this->getGroups();
        $taxonomies = $this->getTaxonomies();
        $matrix = $this->buildMatrix($groups, $taxonomies);

        return view('ahg-acl::term-permissions', [
            'groups' => $groups,
            'taxonomies' => $taxonomies,
            'actions' => self::ACTIONS,
            'matrix' => $matrix,
        ]);
    }

    /**
     * POST /admin/term-permissions - toggle a single cell.
     *
     * Body: { group_id: int, taxonomy_id: int, action: string, grant: bool }
     * Returns: { ok: true, granted: bool }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'required|integer|exists:acl_group,id',
            'taxonomy_id' => 'required|integer|exists:taxonomy,id',
            'action' => 'required|string|in:'.implode(',', array_keys(self::ACTIONS)),
            'grant' => 'required|boolean',
        ]);

        $now = now()->toDateTimeString();

        $existing = DB::table('acl_permission')
            ->where('group_id', $data['group_id'])
            ->where('object_id', $data['taxonomy_id'])
            ->where('action', $data['action'])
            ->first();

        if ($data['grant']) {
            if ($existing) {
                DB::table('acl_permission')->where('id', $existing->id)->update([
                    'grant_deny' => 1,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('acl_permission')->insert([
                    'group_id' => $data['group_id'],
                    'object_id' => $data['taxonomy_id'],
                    'action' => $data['action'],
                    'grant_deny' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'serial_number' => 0,
                ]);
            }
        } else {
            if ($existing) {
                DB::table('acl_permission')->where('id', $existing->id)->delete();
            }
        }

        return response()->json([
            'ok' => true,
            'granted' => (bool) $data['grant'],
        ]);
    }

    /**
     * Get all ACL groups for the matrix rows.
     */
    private function getGroups(): \Illuminate\Support\Collection
    {
        return DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', function ($j) {
                $j->on('gi.id', '=', 'g.id')->where('gi.culture', '=', 'en');
            })
            ->select('g.id', 'gi.name')
            ->orderBy('gi.name')
            ->get();
    }

    /**
     * Get all taxonomies for the matrix columns.
     */
    private function getTaxonomies(): \Illuminate\Support\Collection
    {
        return DB::table('taxonomy as t')
            ->leftJoin('taxonomy_i18n as ti', function ($j) {
                $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', 'en');
            })
            ->select('t.id', 'ti.name', 't.usage')
            ->whereNotNull('ti.name')
            ->orderBy('ti.name')
            ->get();
    }

    /**
     * Build a [group_id][taxonomy_id][action] => bool lookup of the
     * existing acl_permission grants, so the view can pre-tick the
     * matrix without N*M queries.
     */
    private function buildMatrix(\Illuminate\Support\Collection $groups, \Illuminate\Support\Collection $taxonomies): array
    {
        $groupIds = $groups->pluck('id')->all();
        $taxIds = $taxonomies->pluck('id')->all();

        if (empty($groupIds) || empty($taxIds)) {
            return [];
        }

        $rows = DB::table('acl_permission')
            ->whereIn('group_id', $groupIds)
            ->whereIn('object_id', $taxIds)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->where('grant_deny', 1)
            ->get(['group_id', 'object_id', 'action']);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->group_id][$r->object_id][$r->action] = true;
        }

        return $out;
    }
}
