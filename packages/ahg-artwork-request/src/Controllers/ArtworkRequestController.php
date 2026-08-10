<?php

/*
 * Artwork placement requests (#1459, ported from ahgArtworkRequestPlugin 0.3.1).
 *
 * Five screens: ask for a work, see your own requests, review what has been
 * asked for, see what is currently out, and one JSON endpoint the request form
 * calls to report clashes as works are added - plus the approver-settings screen.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArtworkRequest\Controllers;

use AhgArtworkRequest\Services\ArtworkRequestService as Requests;
use AhgCore\Services\AclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArtworkRequestController extends Controller
{
    /**
     * My requests.
     */
    public function index()
    {
        $userId = auth()->id();

        $requests = DB::table('artwork_request')
            ->where('requester_user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->all();

        $works = [];

        foreach ($requests as $r) {
            $works[$r->id] = Requests::objects($r->id);
        }

        return view('ahg-artwork-request::index', compact('requests', 'works'));
    }

    /**
     * Ask for one or more works. Everything submitted is handed back to the form
     * on a validation error, so a missed field never eats the dates, placement
     * and justification the person already typed.
     */
    public function requestForm(Request $request)
    {
        $conflicts = [];
        $errors = [];

        $fields = [
            'requested_from', 'requested_to', 'department', 'purpose', 'justification',
            'placement_building', 'placement_floor', 'placement_room',
            'placement_occupant', 'placement_notes',
        ];
        $values = array_fill_keys($fields, '');
        $works = [];

        if ($request->isMethod('post')) {
            foreach ($fields as $field) {
                $values[$field] = (string) $request->input($field, '');
            }

            $objectIds = Requests::objectIdsFromRequest(
                (array) $request->input('object_ids', []),
                $request->input('object_ids_manual')
            );

            $works = Requests::worksByIds($objectIds);

            $from = $values['requested_from'];
            $to = $values['requested_to'];

            if (! $objectIds) {
                $errors[] = 'Choose at least one work.';
            }

            foreach ($works as $w) {
                if (! $w->exists) {
                    $errors[] = sprintf('There is no record with id %d.', $w->id);
                }
            }

            if (! $from || ! $to) {
                $errors[] = 'Give the dates the work is needed between.';
            } elseif ($to < $from) {
                $errors[] = 'The end date is before the start date.';
            }

            if (! $errors) {
                $me = DB::table('user')->where('id', auth()->id())->first();

                $requestId = Requests::create([
                    'requester_user_id' => auth()->id(),
                    'requester_name' => $me->username ?? null,
                    'requester_email' => $me->email ?? null,
                    'department' => $values['department'],
                    'purpose' => $values['purpose'],
                    'justification' => $values['justification'],
                    'requested_from' => $from,
                    'requested_to' => $to,
                    'placement_building' => $values['placement_building'],
                    'placement_floor' => $values['placement_floor'],
                    'placement_room' => $values['placement_room'],
                    'placement_occupant' => $values['placement_occupant'],
                    'placement_notes' => $values['placement_notes'],
                ], $objectIds, true);

                return redirect()->route('artwork-request.view', ['id' => $requestId])
                    ->with('success', 'Request submitted. The gallery has been notified.');
            }
        }

        // NB: pass as $formErrors, not $errors - $errors is Laravel's shared
        // ViewErrorBag and the theme layout calls $errors->any() on it.
        return view('ahg-artwork-request::request', [
            'conflicts' => $conflicts,
            'formErrors' => $errors,
            'values' => $values,
            'works' => $works,
        ]);
    }

    /**
     * Clashes for one work over a date range, as JSON. A clash is reported, never
     * enforced: the curator may still say yes and needs to see why.
     */
    public function availability(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $conflicts = ($objectId && $from && $to)
            ? Requests::findConflicts($objectId, $from, $to)
            : [];

        return response()->json([
            'object_id' => $objectId,
            'free' => empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * The review queue, and the decision.
     */
    public function review(Request $request)
    {
        if ($request->isMethod('post')) {
            $requestId = (int) $request->input('request_id');
            $decisions = (array) $request->input('decision', []);

            Requests::decide(
                $requestId,
                $decisions,
                $request->input('review_notes'),
                auth()->id(),
                $request->input('decision_channel', 'system')
            );

            return redirect()->route('artwork-request.review')
                ->with('success', 'Decision recorded and the requester notified.');
        }

        $pending = DB::table('artwork_request')
            ->where('status', 'submitted')
            ->orderBy('requested_from')
            ->get()
            ->all();

        $works = [];

        foreach ($pending as $r) {
            $works[$r->id] = Requests::objects($r->id);
        }

        return view('ahg-artwork-request::review', compact('pending', 'works'));
    }

    /**
     * Create the loan record for an approved request. A separate action behind a
     * button: approving decides whether a work may hang somewhere, creating the
     * loan is the moment it physically moves.
     */
    public function createLoan(int $id)
    {
        $loanId = Requests::createLoan($id, auth()->id());

        if ($loanId) {
            return redirect()->route('artwork-request.view', ['id' => $id])
                ->with('success', 'Loan record created. Condition reports and movement are tracked there.');
        }

        return redirect()->route('artwork-request.view', ['id' => $id])
            ->with('error', 'No loan record was created - ahg-loan may not be installed, nothing on this request is approved, or a loan already exists.');
    }

    /**
     * What is out on campus, and what is late.
     */
    public function placements(Request $request)
    {
        $overdueOnly = (bool) $request->input('overdue');
        $placements = Requests::placements($overdueOnly);
        $today = date('Y-m-d');

        return view('ahg-artwork-request::placements', compact('placements', 'overdueOnly', 'today'));
    }

    /**
     * One request, its works, its decision and its log.
     */
    public function view(int $id)
    {
        $requestRow = Requests::get($id);

        if (! $requestRow) {
            abort(404, 'No such request');
        }

        $works = Requests::objects($id);
        $log = Requests::logEntries($id);
        $canReview = AclService::hasPermission(auth()->id(), 'update');

        return view('ahg-artwork-request::view', compact('requestRow', 'works', 'log', 'canReview'));
    }

    /**
     * Who reviews requests, and for which department. Administrator-only: deciding
     * who approves is an administrative function, held tighter than the review
     * queue itself.
     */
    public function approvers(Request $request)
    {
        if (! AclService::isAdministrator()) {
            abort(403, 'Insufficient permissions');
        }

        $errors = [];

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $id = (int) $request->input('approver_id');

            switch ($action) {
                case 'add':
                    $added = Requests::addApprover(
                        (string) $request->input('user_ref'),
                        $request->input('department'),
                        (bool) $request->input('email_notifications')
                    );

                    if (null === $added) {
                        $errors[] = 'No user matches that username or email address.';
                    } else {
                        return redirect()->route('artwork-request.approvers')->with('success', 'Approver added.');
                    }

                    break;

                case 'activate':
                    Requests::setApproverActive($id, true);

                    return redirect()->route('artwork-request.approvers')->with('success', 'Approver enabled.');

                case 'deactivate':
                    Requests::setApproverActive($id, false);

                    return redirect()->route('artwork-request.approvers')->with('success', 'Approver disabled.');

                case 'notifications':
                    Requests::setApproverNotifications($id, (bool) $request->input('on'));

                    return redirect()->route('artwork-request.approvers')->with('success', 'Notification setting saved.');

                case 'remove':
                    Requests::removeApprover($id);

                    return redirect()->route('artwork-request.approvers')->with('success', 'Approver removed.');
            }
        }

        $approvers = Requests::approvers();
        $candidates = Requests::candidateUsers();
        $departments = DB::table('artwork_request')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();

        return view('ahg-artwork-request::approvers', [
            'approvers' => $approvers,
            'candidates' => $candidates,
            'departments' => $departments,
            'formErrors' => $errors,
        ]);
    }
}
