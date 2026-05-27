<?php

/**
 * PublishRequestController - request-to-publish flow with anonymous receipts
 * and a curator inbox (Heratio #745).
 *
 * Public surface:
 *   POST /publish-request                    - submit a new request (anonymous)
 *   GET  /publish-request/receipt/{token}    - check status by opaque token
 *
 * Curator surface (admin middleware):
 *   GET  /admin/publish-requests             - inbox (filterable by status)
 *   GET  /admin/publish-requests/{id}/edit   - per-request review panel
 *   POST /admin/publish-requests/{id}/decision - submit approve/reject/edit
 *
 * The lightweight ahg_publish_request schema runs alongside the legacy
 * request_to_publish / request_to_publish_i18n AtoM-port tables handled by
 * RequestPublishController. The two flows are intentionally independent.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgRequestPublish\Controllers;

use AhgRequestPublish\Notifications\PublishRequestDecisionNotification;
use AhgRequestPublish\Notifications\PublishRequestSubmittedNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublishRequestController extends Controller
{
    /** Canonical status codes (also rows in ahg_dropdown taxonomy publish_request_status). */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EDITED = 'edited';

    public const TABLE = 'ahg_publish_request';

    public const DROPDOWN_TAXONOMY = 'publish_request_status';

    /**
     * Submit a new publish-request. Public, CSRF-exempt (see VerifyCsrfToken
     * except[] entry below). Returns the receipt token on success so the
     * caller can redirect the submitter to /publish-request/receipt/{token}.
     */
    public function submit(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => 'nullable|integer|min:1',
            'submitter_email' => 'required|email|max:190',
            'submitter_name' => 'nullable|string|max:190',
            'message_text' => 'nullable|string|max:10000',
            // captcha-ready: when wired, the route adds 'h-captcha-response' / 'g-recaptcha-response' rules here.
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'error' => 'publish_request table missing - run ServiceProvider boot or install.sql',
            ], 503);
        }

        $token = self::generateToken();

        $id = DB::table(self::TABLE)->insertGetId([
            'information_object_id' => $data['information_object_id'] ?? null,
            'submitter_email' => $data['submitter_email'],
            'submitter_name' => $data['submitter_name'] ?? null,
            'message_text' => $data['message_text'] ?? null,
            'status' => self::STATUS_PENDING,
            'token' => $token,
            'created_at' => now(),
        ]);

        // Best-effort notification stub - never blocks submission.
        try {
            LaravelNotification::route('mail', $data['submitter_email'])
                ->notify(new PublishRequestSubmittedNotification(
                    token: $token,
                    receiptUrl: url('/publish-request/receipt/'.$token),
                    submitterName: $data['submitter_name'] ?? null,
                ));
        } catch (\Throwable $e) {
            Log::warning('publish-request: submission email failed', ['id' => $id, 'error' => $e->getMessage()]);
        }

        // JSON for the AJAX form, HTML redirect for plain submits.
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'id' => $id,
                'token' => $token,
                'receipt_url' => url('/publish-request/receipt/'.$token),
                'status' => self::STATUS_PENDING,
            ], 201);
        }

        return redirect('/publish-request/receipt/'.$token)
            ->with('success', 'Your request has been submitted.');
    }

    /**
     * Anonymous status receipt. The token is the only authorization - any
     * holder of the URL can see status + curator notes. No auth required.
     */
    public function receipt(string $token)
    {
        if (! Schema::hasTable(self::TABLE)) {
            abort(503, 'publish_request table missing');
        }

        // Token shape guard before hitting the DB - cheap defence against
        // probing. 40 hex chars matches generateToken() output.
        if (! preg_match('/^[a-f0-9]{40}$/i', $token)) {
            abort(404);
        }

        $row = DB::table(self::TABLE)->where('token', $token)->first();

        if (! $row) {
            abort(404, 'Receipt not found');
        }

        $object = null;
        if ($row->information_object_id && Schema::hasTable('information_object')) {
            $culture = app()->getLocale();
            $object = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->where('io.id', '=', $row->information_object_id)
                ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
                ->first();
        }

        return view('ahg-request-publish::receipt', [
            'row' => $row,
            'object' => $object,
        ]);
    }

    /**
     * Curator inbox - filterable by status. Admin-only (route group).
     */
    public function inbox(Request $request)
    {
        if (! Schema::hasTable(self::TABLE)) {
            return view('ahg-request-publish::inbox', [
                'tableExists' => false,
                'rows' => [],
                'counts' => [],
                'status' => 'all',
            ]);
        }

        $status = $request->input('status', 'all');
        $valid = ['all', self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_EDITED];
        if (! in_array($status, $valid, true)) {
            $status = 'all';
        }

        $q = DB::table(self::TABLE.' as pr')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('pr.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug as s', 'pr.information_object_id', '=', 's.object_id')
            ->select(
                'pr.id', 'pr.submitter_email', 'pr.submitter_name', 'pr.status',
                'pr.created_at', 'pr.decided_at', 'pr.information_object_id',
                'ioi.title as object_title', 's.slug as object_slug'
            )
            ->orderByDesc('pr.created_at');

        if ($status !== 'all') {
            $q->where('pr.status', $status);
        }

        $rows = $q->limit(200)->get();

        $counts = DB::table(self::TABLE)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();
        $counts['all'] = array_sum($counts);

        return view('ahg-request-publish::inbox', [
            'tableExists' => true,
            'rows' => $rows,
            'counts' => $counts,
            'status' => $status,
        ]);
    }

    /**
     * Per-request review panel - shows original submission and decision form.
     */
    public function edit(int $id)
    {
        if (! Schema::hasTable(self::TABLE)) {
            abort(503);
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (! $row) {
            abort(404);
        }

        $object = null;
        if ($row->information_object_id && Schema::hasTable('information_object')) {
            $culture = app()->getLocale();
            $object = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->where('io.id', '=', $row->information_object_id)
                ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
                ->first();
        }

        $statuses = [];
        if (Schema::hasTable('ahg_dropdown')) {
            $statuses = DB::table('ahg_dropdown')
                ->where('taxonomy', self::DROPDOWN_TAXONOMY)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->pluck('label', 'code')
                ->toArray();
        }
        if (empty($statuses)) {
            $statuses = [
                self::STATUS_PENDING => 'Pending',
                self::STATUS_APPROVED => 'Approved',
                self::STATUS_REJECTED => 'Rejected',
                self::STATUS_EDITED => 'Edited',
            ];
        }

        return view('ahg-request-publish::panel', [
            'row' => $row,
            'object' => $object,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Submit a curator decision (status + notes + optional edited message).
     */
    public function decision(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', [
                self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_EDITED,
            ]),
            'curator_notes' => 'nullable|string|max:10000',
            'message_text' => 'nullable|string|max:10000',
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            abort(503);
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (! $row) {
            abort(404);
        }

        $update = [
            'status' => $data['status'],
            'curator_notes' => $data['curator_notes'] ?? null,
            'decided_at' => now(),
            'decided_by_user_id' => Auth::id(),
        ];

        // 'edited' means the curator rewrote the submission text - persist it.
        if ($data['status'] === self::STATUS_EDITED && array_key_exists('message_text', $data)) {
            $update['message_text'] = $data['message_text'];
        }

        DB::table(self::TABLE)->where('id', $id)->update($update);

        // Best-effort decision email to submitter.
        try {
            LaravelNotification::route('mail', $row->submitter_email)
                ->notify(new PublishRequestDecisionNotification(
                    token: $row->token,
                    status: $data['status'],
                    receiptUrl: url('/publish-request/receipt/'.$row->token),
                    curatorNotes: $data['curator_notes'] ?? null,
                ));
        } catch (\Throwable $e) {
            Log::warning('publish-request: decision email failed', ['id' => $id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('publish-requests.inbox')
            ->with('success', 'Decision recorded.');
    }

    /**
     * 40-char hex token. sha1 of (random_bytes(32) + microtime) - cheap and
     * collision-resistant enough for an opaque receipt URL.
     */
    public static function generateToken(): string
    {
        return sha1(random_bytes(32).microtime(true).Str::random(16));
    }
}
