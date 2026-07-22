<?php

/**
 * AccessRequestController - Controller for Heratio
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

namespace AhgAccessRequest\Controllers;

use AhgAccessRequest\Services\AccessRequestService;
use AhgCore\Services\AclService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccessRequestController extends Controller
{
    /** Levels of description that count as a "collection" for request scoping. */
    public const COLLECTION_LEVELS = ['Collection', 'Fonds', 'Subfonds', 'Series', 'Subseries'];

    /** Valid request scopes: the whole holdings, one collection, or one item. */
    public const SCOPES = ['all', 'collection', 'item'];

    public function __construct(
        protected AccessRequestService $service
    ) {}

    /**
     * Browse all access requests.
     */
    public function browse()
    {
        $requests = $this->service->getAllRequests();

        return view('ahg-access-request::browse', compact('requests'));
    }

    /**
     * New access request form.
     */
    public function create()
    {
        $classifications = \Illuminate\Support\Facades\Schema::hasTable('security_classification')
            ? \Illuminate\Support\Facades\DB::table('security_classification')
                ->orderBy('level')
                ->select('id', 'code', 'name', 'level')
                ->get()
            : collect();

        return view('ahg-access-request::new', [
            'classifications' => $classifications,
            'collections' => $this->collectionOptions(),
        ]);
    }

    /**
     * Resolved title for a scope target, stored on the scope row so reviewers
     * still see what was asked for if the record is later renamed or removed.
     */
    protected function objectTitle(int $objectId): ?string
    {
        $culture = app()->getLocale();

        $title = \Illuminate\Support\Facades\DB::table('information_object_i18n')
            ->where('id', $objectId)->where('culture', $culture)->value('title');

        if ($title === null || trim((string) $title) === '') {
            // Fall back to the record's source culture before giving up.
            $title = \Illuminate\Support\Facades\DB::table('information_object_i18n as i18n')
                ->join('information_object as io', 'io.id', '=', 'i18n.id')
                ->whereColumn('i18n.culture', 'io.source_culture')
                ->where('i18n.id', $objectId)
                ->value('i18n.title');
        }

        return $title !== null && trim((string) $title) !== '' ? mb_substr((string) $title, 0, 500) : null;
    }

    /**
     * Selectable "collections" for a collection-scoped request.
     *
     * Deliberately keyed off level of description rather than top-level-ness.
     * On atom.theahg.co.za 418,541 records sit directly under the root, so a
     * "top-level records" list is unusable - but by level there are only 65
     * genuine collections (Collection 36, Series 15, Fonds 13, Subfonds 1),
     * which is both a sensible dropdown and the archivally correct meaning of
     * "collection".
     *
     * @return \Illuminate\Support\Collection
     */
    protected function collectionOptions()
    {
        $culture = app()->getLocale();

        try {
            return \Illuminate\Support\Facades\DB::table('information_object as io')
                ->join('term_i18n as lvl', function ($j) use ($culture) {
                    $j->on('lvl.id', '=', 'io.level_of_description_id')
                        ->where('lvl.culture', '=', $culture);
                })
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', $culture);
                })
                ->whereIn('lvl.name', self::COLLECTION_LEVELS)
                ->where('io.id', '>', 1)
                ->select('io.id', 'i18n.title', 'lvl.name as level_name')
                ->orderBy('i18n.title')
                ->limit(500)
                ->get()
                ->filter(fn ($r) => trim((string) $r->title) !== '')
                ->values();
        } catch (\Throwable $e) {
            // A missing level taxonomy must not take the whole form down; the
            // requester can still choose "everything" or a single item.
            return collect();
        }
    }

    /**
     * Request access to a specific object.
     */
    public function requestObject(Request $request, string $slug)
    {
        return view('ahg-access-request::request-object', compact('slug'));
    }

    /**
     * My requests listing.
     */
    public function myRequests()
    {
        $userId = auth()->id();
        $requests = $this->service->getMyRequests($userId);

        return view('ahg-access-request::my-requests', compact('requests'));
    }

    /**
     * Pending requests (admin/approver view).
     */
    public function pending()
    {
        $requests = $this->service->getPendingRequests();

        return view('ahg-access-request::pending', compact('requests'));
    }

    /**
     * View a single request.
     */
    public function view(string $id)
    {
        $accessRequest = $this->service->getRequest((int) $id);
        abort_unless($accessRequest, 404);

        // Ownership gate (#1366): a request exposes the requester's identity +
        // justification + the target record's classification. Only the owner or an
        // admin/approver may view it (was unguarded — any authed user by id).
        abort_unless(
            (int) ($accessRequest->user_id ?? 0) === (int) auth()->id()
                || AclService::canAdmin(auth()->id()),
            403
        );

        return view('ahg-access-request::view', compact('accessRequest'));
    }

    /**
     * Manage approvers.
     */
    public function approvers()
    {
        $approvers = $this->service->getApprovers();

        return view('ahg-access-request::approvers', compact('approvers'));
    }

    /**
     * Store a new (general) access request submitted from the new.blade.php form.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'request_type' => 'required|string|max:64',
            'description' => 'required|string|max:2000',
            'justification' => 'nullable|string|max:2000',
            'urgency' => 'nullable|in:low,normal,high,urgent',
            'requested_classification_id' => 'nullable|integer|exists:security_classification,id',
            // Scope: everything, one collection, or one item. The target is
            // required for the two scoped forms and ignored for 'all'.
            'scope_type' => 'required|in:'.implode(',', self::SCOPES),
            'scope_collection_id' => 'required_if:scope_type,collection|nullable|integer|exists:information_object,id',
            'scope_item_id' => 'required_if:scope_type,item|nullable|integer|exists:information_object,id',
        ], [
            'scope_collection_id.required_if' => __('Choose which collection you need access to.'),
            'scope_item_id.required_if' => __('Choose which item you need access to.'),
        ]);

        $scopeType = $validated['scope_type'];
        $targetId = match ($scopeType) {
            'collection' => (int) $validated['scope_collection_id'],
            'item' => (int) $validated['scope_item_id'],
            default => null,
        };

        $payload = [
            'subject' => $validated['subject'],
            'request_type' => $validated['request_type'],
            // #1366 — explicit classification key (null → baseline Public default).
            'requested_classification_id' => $validated['requested_classification_id'] ?? null,
            'reason' => 'Subject: '.$validated['subject']."\n\n".$validated['description'],
            'justification' => $validated['justification'] ?? null,
            'urgency' => $validated['urgency'] ?? 'normal',
            'scope_type' => $scopeType,
        ];

        if ($targetId) {
            $payload['target_object_id'] = $targetId;
            $payload['target_object_type'] = 'information_object';
            $payload['target_object_title'] = $this->objectTitle($targetId);
            // A collection request covers its children; an item request does not.
            $payload['include_descendants'] = $scopeType === 'collection';
        }

        $this->service->createRequest(auth()->id(), $payload);

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Access request submitted.');
    }

    /**
     * Approve an access request.
     */
    public function approve(Request $request, string $id)
    {
        $this->service->approveRequest((int) $id, auth()->id(), $request->get('notes'));

        return redirect()->route('accessRequest.pending')->with('notice', 'Request approved.');
    }

    /**
     * Deny an access request.
     */
    public function deny(Request $request, string $id)
    {
        $this->service->denyRequest((int) $id, auth()->id(), $request->get('reason'));

        return redirect()->route('accessRequest.pending')->with('notice', 'Request denied.');
    }

    /**
     * Add an approver.
     */
    public function addApprover(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $this->service->addApprover($validated['user_id']);

        return redirect()->route('accessRequest.approvers')->with('notice', 'Approver added.');
    }

    /**
     * Remove an approver.
     */
    public function removeApprover(string $id)
    {
        $this->service->removeApprover((int) $id);

        return redirect()->route('accessRequest.approvers')->with('notice', 'Approver removed.');
    }

    /**
     * Cancel an access request (by the requesting user).
     */
    public function cancel(string $id)
    {
        $this->service->cancelRequest((int) $id, auth()->id());

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Access request cancelled.');
    }

    /**
     * Store a new object-specific access request.
     */
    public function storeObjectRequest(Request $request)
    {
        $validated = $request->validate([
            'object_id' => 'required|integer',
            'reason' => 'required|string|max:2000',
        ]);

        // #1366 — object request: the target IO goes to access_request_scope
        // (request_type=object); classification defaults to baseline Public.
        $this->service->createRequest(auth()->id(), [
            'request_type' => 'object',
            'target_object_id' => $validated['object_id'],
            'target_object_type' => 'information_object',
            'reason' => $validated['reason'],
        ]);

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Object access request submitted.');
    }
}
