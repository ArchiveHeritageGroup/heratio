<?php

/**
 * AssignmentController - Heratio
 *
 * Assign / Workflow feature of the AHG Authority Resolution Engine. Handles
 * the three HTTP entry points for assigning mentions to an archivist:
 *
 *   POST /admin/authority-resolution/review/{mention}/assign
 *        - assign a single mention from the review screen.
 *   POST /admin/authority-resolution/queue/assign
 *        - assign one or many mentions from the queue (batch assign).
 *   GET  /admin/authority-resolution/archivists.json
 *        - JSON list of eligible assignees for the picker dropdowns.
 *
 * All three sit under the same admin-gated route group as the rest of the
 * auth-res tree. The real work is delegated to AssignmentService, which
 * routes each mention through the ahg-workflow plugin.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Http\Controllers;

use AhgAuthorityResolution\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignments,
    ) {}

    /**
     * POST /admin/authority-resolution/review/{mention}/assign
     *
     * Assign a single mention from the review screen. Body: archivist_user_id.
     */
    public function assignFromReview(Request $request, int $mention)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $data = $request->validate([
            'archivist_user_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';
        $result = $this->assignments->assign(
            $mention,
            (int) $data['archivist_user_id'],
            $userId,
            $reason !== '' ? $reason : null
        );

        if (!$result['ok']) {
            return back()->withErrors(['assign' => $result['error'] ?? 'Assignment failed.']);
        }

        $name = $this->archivistName((int) $data['archivist_user_id']);
        $notice = "Mention #{$mention} assigned to {$name}.";
        if ($result['workflow_task_id']) {
            $notice .= " Workflow task #{$result['workflow_task_id']}.";
        }

        return back()->with('notice', $notice);
    }

    /**
     * POST /admin/authority-resolution/queue/assign
     *
     * Single or batch assign from the queue. Body: mention_ids[] +
     * archivist_user_id. Handles 1..N mentions.
     */
    public function assignFromQueue(Request $request)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $data = $request->validate([
            'archivist_user_id' => ['required', 'integer', 'min:1'],
            'mention_ids' => ['required', 'array', 'min:1'],
            'mention_ids.*' => ['integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';
        $result = $this->assignments->assignBatch(
            $data['mention_ids'],
            (int) $data['archivist_user_id'],
            $userId,
            $reason !== '' ? $reason : null
        );

        $name = $this->archivistName((int) $data['archivist_user_id']);

        if ($result['assigned'] === 0) {
            $msg = 'No mentions were assigned.';
            if (!empty($result['errors'])) {
                $msg .= ' ' . implode(' ', array_slice($result['errors'], 0, 5));
            }
            return back()->withErrors(['assign' => $msg]);
        }

        $notice = sprintf(
            '%d mention(s) assigned to %s.%s',
            $result['assigned'],
            $name,
            $result['failed'] > 0 ? " {$result['failed']} failed." : ''
        );

        $redirect = back()->with('notice', $notice);
        if (!empty($result['errors'])) {
            $redirect->withErrors(['assign' => implode(' ', array_slice($result['errors'], 0, 5))]);
        }

        return $redirect;
    }

    /**
     * GET /admin/authority-resolution/archivists.json
     *
     * JSON list of eligible assignees for the picker dropdowns.
     */
    public function archivistsJson(): JsonResponse
    {
        return response()->json([
            'archivists' => $this->assignments->archivists(),
        ]);
    }

    /**
     * Best-effort display name for a user id (for flash messages).
     */
    private function archivistName(int $userId): string
    {
        $row = DB::table('user')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->select('user.username', 'actor_i18n.authorized_form_of_name as display_name')
            ->first();

        if (!$row) {
            return 'User #' . $userId;
        }
        $name = trim((string) ($row->display_name ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->username ?? ''));
        }
        return $name !== '' ? $name : ('User #' . $userId);
    }
}
