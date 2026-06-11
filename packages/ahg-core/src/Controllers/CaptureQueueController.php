<?php

/**
 * CaptureQueueController - Heratio ahg-core
 *
 * heratio#1205 "race against loss": the operator-facing capture queue. Where
 * CapturePriorityController renders the read-only at-risk register, this
 * controller drives the actionable workflow built on CaptureQueueService - it
 * lists the queue (filterable by status), and accepts POST actions to add a
 * record to the queue, change a row's status, (re)assign it, and remove it.
 *
 * Admin-gated via the route group's `auth` middleware (matching the rest of the
 * /admin/* ahg-core surface). All writes are confined to the ahg_capture_queue
 * side table through the service; no AtoM base tables are written. Status values
 * come from the Dropdown Manager group `capture_queue_status` only. Every action
 * is resilient: the service fails safe (no-op) when its table or the dropdown
 * group is missing, so the queue can never 500 a fresh / mid-migration install.
 *
 * Multi-segment paths (/admin/capture-priority/queue...) keep this clear of the
 * single-segment /{slug} archival-record catch-all.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\CaptureQueueService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CaptureQueueController extends Controller
{
    public function __construct(private CaptureQueueService $service) {}

    /**
     * The capture queue list. Optional ?status= filter (a single dropdown code).
     * Renders an empty, honest state when the feature is unavailable rather than
     * 500ing - the service already swallows failures and returns empty.
     */
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $statuses = $this->service->statuses();

        // Only honour a filter that is a real, configured status code.
        $validCodes = array_map(fn ($s) => $s['code'], $statuses);
        if ($status !== '' && ! in_array($status, $validCodes, true)) {
            $status = '';
        }

        return view('ahg-core::capture-priority.queue', [
            'available' => $this->service->isAvailable(),
            'rows' => $this->service->list($status !== '' ? ['status' => $status] : []),
            'statuses' => $statuses,
            'counts' => $this->service->counts(),
            'filterStatus' => $status,
        ]);
    }

    /**
     * Add a record to the capture queue (idempotent). The priority_score snapshot
     * is taken from the posted score (the register row's current score). Redirects
     * back to wherever the action was triggered with a flash message.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => ['required', 'integer', 'min:2'],
            'priority_score' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'string', 'max:190'],
        ]);

        $id = $this->service->add(
            (int) $data['information_object_id'],
            (int) ($data['priority_score'] ?? 0),
            $data['note'] ?? null,
            $data['assigned_to'] ?? null,
        );

        return $this->backWith(
            $id !== null,
            $id !== null ? __('Record added to the capture queue.') : __('The capture queue is not available right now.'),
        );
    }

    /**
     * Change a queue row's status. Status must be a configured dropdown code; the
     * service rejects anything else, so an out-of-taxonomy value is a no-op.
     */
    public function setStatus(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'max:40'],
        ]);

        $ok = $this->service->setStatus((int) $data['id'], (string) $data['status']);

        return $this->backWith($ok, $ok ? __('Capture status updated.') : __('That status could not be applied.'));
    }

    /**
     * (Re)assign or clear the operator responsible for a queue row.
     */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'assigned_to' => ['nullable', 'string', 'max:190'],
        ]);

        $ok = $this->service->assign((int) $data['id'], $data['assigned_to'] ?? null);

        return $this->backWith($ok, $ok ? __('Assignment updated.') : __('The assignment could not be updated.'));
    }

    /**
     * Remove a record from the capture queue.
     */
    public function remove(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $ok = $this->service->remove((int) $data['id']);

        return $this->backWith($ok, $ok ? __('Record removed from the capture queue.') : __('The record could not be removed.'));
    }

    /**
     * Redirect back with a one-line flash. Keeps the four POST actions uniform.
     */
    private function backWith(bool $ok, string $message)
    {
        return redirect()->back()->with($ok ? 'status' : 'error', $message);
    }
}
