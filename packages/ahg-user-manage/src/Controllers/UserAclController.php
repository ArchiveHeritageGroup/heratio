<?php

/**
 * UserAclController - Heratio
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

namespace AhgUserManage\Controllers;

use AhgAcl\Services\AclService;
use AhgUserManage\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user ACL editor (closes #52). Mirrors the group-level editor at
 * /admin/acl/group/{id}/{tab} with four entity tabs:
 *   - /user/{slug}/edit-information-object-acl
 *   - /user/{slug}/edit-actor-acl
 *   - /user/{slug}/edit-repository-acl
 *   - /user/{slug}/edit-term-acl
 *
 * Each tab uses AclService::applyUserAclForm + getUserPermissionsByClass
 * (the user-level mirrors of the existing group methods). The edit blades
 * already exist as stubs in packages/ahg-user-manage/resources/views/;
 * this controller wires them.
 *
 * The four index routes (referenced by the existing _acl-menu partial)
 * redirect to the corresponding edit page - the AtoM-style "effective
 * permissions across groups" matrix view is left as a future enhancement
 * (the edit form already shows the existing per-user perms in an editable
 * table, which is sufficient for ops).
 */
class UserAclController extends Controller
{
    private UserService $users;
    private AclService $acl;

    public function __construct(UserService $users, AclService $acl)
    {
        $this->users = $users;
        $this->acl = $acl;
    }

    public function editInformationObjectAcl(Request $request, string $slug)
    {
        return $this->editTab(
            $request, $slug,
            'QubitInformationObject',
            AclService::IO_ACTIONS,
            'ahg-user-manage::edit-information-object-acl',
            __('Archival description'),
            'user.editInformationObjectAcl',
            __('Information object permissions saved.'),
        );
    }

    public function editActorAcl(Request $request, string $slug)
    {
        return $this->editTab(
            $request, $slug,
            'QubitActor',
            AclService::ACTOR_ACTIONS,
            'ahg-user-manage::edit-actor-acl',
            __('Authority record'),
            'user.editActorAcl',
            __('Actor permissions saved.'),
        );
    }

    public function editRepositoryAcl(Request $request, string $slug)
    {
        return $this->editTab(
            $request, $slug,
            'QubitRepository',
            AclService::REPOSITORY_ACTIONS,
            'ahg-user-manage::edit-repository-acl',
            __('Archival institution'),
            'user.editRepositoryAcl',
            __('Repository permissions saved.'),
        );
    }

    public function editTermAcl(Request $request, string $slug)
    {
        return $this->editTab(
            $request, $slug,
            'QubitTerm',
            AclService::TERM_ACTIONS,
            'ahg-user-manage::edit-term-acl',
            __('Taxonomy'),
            'user.editTermAcl',
            __('Taxonomy permissions saved.'),
        );
    }

    // Index routes redirect to the corresponding edit form. The existing
    // _acl-menu partial links here; the edit form already shows current
    // perms in a table, which is enough for v1. AtoM's denormalised
    // "permissions across groups" matrix view can ship as an enhancement.
    public function indexInformationObjectAcl(Request $request, string $slug) {
        return redirect()->route('user.editInformationObjectAcl', ['slug' => $slug]);
    }
    public function indexActorAcl(Request $request, string $slug) {
        return redirect()->route('user.editActorAcl', ['slug' => $slug]);
    }
    public function indexRepositoryAcl(Request $request, string $slug) {
        return redirect()->route('user.editRepositoryAcl', ['slug' => $slug]);
    }
    public function indexTermAcl(Request $request, string $slug) {
        return redirect()->route('user.editTermAcl', ['slug' => $slug]);
    }

    /**
     * Shared edit-tab body. Resolves the user, applies the ACL form on
     * POST, then renders the matching blade with the current per-user
     * permissions + repositories list (for the "add new" select on the
     * info-object tab).
     */
    private function editTab(
        Request $request,
        string $slug,
        string $className,
        array $allowedActions,
        string $view,
        string $entityLabel,
        string $editRouteName,
        string $successMessage,
    ) {
        $user = $this->users->getBySlug($slug);
        if (!$user) abort(404, 'User not found.');

        if ($request->isMethod('post')) {
            $this->acl->applyUserAclForm(
                (int) $user->id,
                (array) $request->input('acl', $request->input('permissions', [])),
                $allowedActions,
                $className,
            );
            return redirect()->route($editRouteName, ['slug' => $user->slug])
                ->with('success', $successMessage);
        }

        $permissions = $this->acl->getUserPermissionsByClass((int) $user->id, $className);

        // Repositories list for the "add new permission" select (used by
        // the IO tab; harmless on the others - blade conditionally renders).
        $repositories = DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'r.id')->where('ai.culture', '=', 'en');
            })
            ->select('r.id', 'ai.authorized_form_of_name')
            ->orderBy('ai.authorized_form_of_name')
            ->get();

        return view($view, [
            'user'         => $user,
            'permissions'  => $permissions,
            'repositories' => $repositories,
            'entityLabel'  => $entityLabel,
            'allowedActions' => $allowedActions,
        ]);
    }
}
