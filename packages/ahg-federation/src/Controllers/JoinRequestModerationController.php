<?php

/**
 * JoinRequestModerationController - admin moderation of public "Join the
 * network" requests (#1203 slice).
 *
 *   GET  /federation/join-requests          dashboard: the moderation queue
 *   POST /federation/join-requests/{id}      apply a status transition
 *
 * Admin-gated (auth + admin middleware in the route group). Mirrors the
 * pending / reviewing / approved / declined moderation pattern used elsewhere
 * (glossary / transcription review).
 *
 * Approving a request marks it 'approved' ONLY. It does NOT auto-create a
 * federation_member: turning an approved institution into an actual network
 * member stays the admin's deliberate action via the existing member registry
 * (UnionMemberController, /federation/members). This keeps member creation a
 * conscious, reviewed step rather than a side effect of clicking "approve".
 *
 * Fresh code under #1203 - never touches the locked F3 FederationController /
 * edit-peer view.
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

use AhgFederation\Services\JoinRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class JoinRequestModerationController extends Controller
{
    public function __construct(private JoinRequestService $service)
    {
    }

    /** The moderation dashboard. */
    public function index(Request $request)
    {
        $filter = $request->query('status');
        $filter = is_string($filter) && in_array($filter, JoinRequestService::STATUSES, true)
            ? $filter
            : null;

        $queue = $this->service->queue($filter);

        return view('ahg-federation::join.moderate', [
            'requests' => $queue['requests'],
            'counts' => $queue['counts'],
            'total' => $queue['total'],
            'filter' => $filter,
            'statuses' => JoinRequestService::STATUSES,
        ]);
    }

    /** Apply a moderation status transition. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', JoinRequestService::STATUSES)],
            'review_note' => ['nullable', 'string', 'max:65535'],
        ]);

        $reviewer = $this->reviewerLabel();

        $ok = $this->service->setStatus(
            $id,
            $data['status'],
            $reviewer,
            $data['review_note'] ?? null
        );

        return redirect()
            ->route('federation.joinRequests.index')
            ->with($ok ? 'status' : 'error', $ok
                ? __('Request updated to :status.', ['status' => __(ucfirst($data['status']))])
                : __('Could not update that request.'));
    }

    /** Best-effort human label for the acting admin. */
    protected function reviewerLabel(): ?string
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return null;
            }

            foreach (['email', 'username', 'name'] as $attr) {
                $val = $user->{$attr} ?? null;
                if (is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
            }

            return 'user#'.($user->id ?? '?');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
