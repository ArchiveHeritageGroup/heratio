<?php

/**
 * UnionMemberController - admin CRUD for the federation member registry plus
 * the opt-in sharing config and a manual "Publish now" trigger.
 *
 *   GET  /federation/members                dashboard: members + share config
 *   GET  /federation/members/add            add-member form
 *   GET  /federation/members/{id}/edit      edit-member form
 *   POST /federation/members/save           insert / update a member
 *   POST /federation/members/{id}/delete    delete a member
 *   POST /federation/members/share          save the opt-in sharing config
 *   POST /federation/members/publish        run ahg:federation-publish now
 *
 * Admin-gated (auth + admin middleware in the route group). Fresh code under
 * #1203 - never touches the locked F3 FederationController / edit-peer view.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\UnionCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class UnionMemberController extends Controller
{
    public function __construct(private UnionCatalogueService $service)
    {
    }

    public function index()
    {
        return view('ahg-federation::union.members', [
            'members' => $this->service->members(),
            'share' => $this->service->shareSetting(),
            'unionCount' => $this->service->unionRecordCount(),
            'self' => $this->service->selfMember(),
        ]);
    }

    public function create()
    {
        return view('ahg-federation::union.edit-member', [
            'member' => null,
        ]);
    }

    public function edit(int $id)
    {
        $member = $this->service->findMember($id);
        if (! $member) {
            return redirect()
                ->route('union.members.index')
                ->with('error', __('That member could not be found.'));
        }

        return view('ahg-federation::union.edit-member', [
            'member' => $member,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'string', 'max:1024'],
            'contact' => ['nullable', 'string', 'max:255'],
            'share_scope' => ['nullable', 'string', 'max:65535'],
            'is_self' => ['nullable'],
            'is_enabled' => ['nullable'],
        ]);

        $id = ! empty($data['id']) ? (int) $data['id'] : null;
        $saved = $this->service->saveMember($data, $id);

        if ($saved === null) {
            return redirect()
                ->route('union.members.index')
                ->with('error', __('Could not save the member. The federation tables may not be installed yet.'));
        }

        return redirect()
            ->route('union.members.index')
            ->with('status', __('Member saved.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $ok = $this->service->deleteMember($id);

        return redirect()
            ->route('union.members.index')
            ->with($ok ? 'status' : 'error',
                $ok ? __('Member removed.') : __('Could not remove the member.'));
    }

    public function saveShare(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'share_enabled' => ['nullable'],
            'published_only' => ['nullable'],
            'min_level_id' => ['nullable', 'integer'],
        ]);

        $ok = $this->service->saveShareSetting($data);

        return redirect()
            ->route('union.members.index')
            ->with($ok ? 'status' : 'error',
                $ok ? __('Sharing settings saved.') : __('Could not save the sharing settings.'));
    }

    public function publish(): RedirectResponse
    {
        try {
            Artisan::call('ahg:federation-publish');
            $out = trim(Artisan::output());
        } catch (\Throwable $e) {
            return redirect()
                ->route('union.members.index')
                ->with('error', __('Publish failed: ').$e->getMessage());
        }

        return redirect()
            ->route('union.members.index')
            ->with('status', __('Publish run complete.'))
            ->with('publishOutput', $out);
    }
}
