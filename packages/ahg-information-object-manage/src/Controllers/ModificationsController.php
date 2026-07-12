<?php

/**
 * ModificationsController - Per-record audit-trail drill-down for one IO.
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
 *
 * Migrated from PSIS atom-ahg-plugins/ahgInformationObjectManagePlugin/lib/Action/
 * informationObjectModificationsAction.class.php (issue #742, twin issue
 * atom-ahg-plugins#87).
 *
 * Resolves an information_object by slug, then paginates the ahg_audit_log
 * rows that match entity_type='information_object' + entity_id={IO}. Renders
 * the existing resources/views/modifications.blade.php template (date /
 * action / user columns).
 *
 * Lock note: explicitly NOT routed through InformationObjectController or
 * any file in the show.blade.php render path. This is a brand-new sibling
 * controller added under the one-shot unlock of
 * packages/ahg-information-object-manage/ for issue #742.
 */

namespace AhgInformationObjectManage\Controllers;

use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModificationsController extends Controller
{
    /**
     * GET /informationobject/{slug}/modifications
     *
     * Audit-trail rows filtered by entity_type='information_object' AND
     * entity_id = {IO}. Most recent first. Paginated 25/page.
     */
    public function index(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $resource = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        if (!$resource) {
            abort(404);
        }

        // Per-record read gate: the audit trail leaks staff usernames/emails and
        // the edit history of draft records. Mirror InformationObjectController::show()
        // - admins/editors pass (built-in bypass), unauthorised users are denied.
        abort_unless(\AhgCore\Services\AclService::hasPermission(\Illuminate\Support\Facades\Auth::id(), 'read', (int) $resource->id), 403);

        // The ahg_audit_log table is shipped by ahg-audit-trail. If the
        // package is not installed (fresh dev sandbox) bail gracefully
        // with an empty list instead of 500-ing.
        if (!Schema::hasTable('ahg_audit_log')) {
            return view('ahg-io-manage::modifications', [
                'resource' => $resource,
                'modifications' => [],
                'pager' => null,
            ]);
        }

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;

        $base = DB::table('ahg_audit_log')
            ->where('entity_type', 'information_object')
            ->where('entity_id', $resource->id);

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $modifications = $rows->map(function ($r) {
            return (object) [
                'createdAt' => $r->created_at,
                'actionTypeName' => $this->humanAction($r->action ?? '', $r->action_name ?? null),
                'userId' => $r->user_id ? (int) $r->user_id : null,
                'userName' => $r->username ?: ($r->user_email ?: __('System')),
            ];
        })->all();

        $pager = new SimplePager([
            'hits' => $modifications,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
        ]);

        return view('ahg-io-manage::modifications', [
            'resource' => $resource,
            'modifications' => $modifications,
            'pager' => $pager,
        ]);
    }

    /**
     * Map raw audit-log action codes to the user-facing strings PSIS uses
     * in the same column. Falls back to action_name then the raw action
     * token so we never render an empty cell.
     */
    private function humanAction(string $action, ?string $actionName): string
    {
        $map = [
            'create' => __('Create'),
            'created' => __('Create'),
            'update' => __('Update'),
            'updated' => __('Update'),
            'edit' => __('Update'),
            'delete' => __('Delete'),
            'deleted' => __('Delete'),
            'finding_aid_upload' => __('Finding aid uploaded'),
            'finding_aid_generate' => __('Finding aid generated'),
            'finding_aid_delete' => __('Finding aid deleted'),
            'move' => __('Move'),
            'rename' => __('Rename'),
            'publish' => __('Publication status changed'),
            'login' => __('Login'),
            'logout' => __('Logout'),
        ];

        $key = strtolower($action);
        if (isset($map[$key])) {
            return $map[$key];
        }

        if ($actionName) {
            return $actionName;
        }

        return $action ?: __('Modification');
    }
}
