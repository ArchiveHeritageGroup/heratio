<?php

/**
 * TransferController - "Send to Archivematica" trigger + status endpoint.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Controllers;

use AhgArchivematica\Services\TransferService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Direction-2 UI/API surface: kick off a transfer for an information_object
 * and report its current status.
 *
 * Routes (registered by the package ServiceProvider - not edited here):
 *   POST /admin/archivematica/transfer/{objectId}  -> trigger() (name archivematica.transfer.trigger)
 *   GET  /admin/archivematica/status/{objectId}    -> status()  (name archivematica.transfer.status)
 *
 * The transfer trigger responds with JSON for XHR callers and redirects back
 * with a flash message for a plain form post, so the transfer-panel button
 * works with or without JS. trigger() is the route target; send() is the
 * task-named alias that carries the actual logic.
 */
class TransferController extends Controller
{
    private TransferService $transfers;

    public function __construct(TransferService $transfers)
    {
        $this->transfers = $transfers;
    }

    /**
     * Route target for archivematica.transfer.trigger. Delegates to send().
     */
    public function trigger(Request $request, int $objectId)
    {
        return $this->send($request, $objectId);
    }

    /**
     * Trigger an Archivematica transfer for a record.
     */
    public function send(Request $request, int $objectId)
    {
        $validated = $request->validate([
            'source_path' => 'nullable|string|max:2048',
            'type'        => 'nullable|string|max:64',
        ]);

        try {
            $jobId = $this->transfers->send(
                $objectId,
                $validated['source_path'] ?? null,
                $validated['type'] ?? 'standard'
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'    => false,
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Archivematica transfer failed: ' . $e->getMessage());
        }

        $job = DB::table('am_job')->where('id', $jobId)->first();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => true,
                'job_id' => $jobId,
                'job'    => $job,
            ]);
        }

        return back()->with('success', 'Transfer sent to Archivematica (job #' . $jobId . ').');
    }

    /**
     * Return the current am_job + am_link status for a record. Used by the
     * status panel's polling fetch.
     */
    public function status(Request $request, int $objectId): JsonResponse
    {
        $job = null;
        if (Schema::hasTable('am_job')) {
            $job = DB::table('am_job')
                ->where('object_id', $objectId)
                ->where('direction', 'to_am')
                ->orderByDesc('id')
                ->first();
        }

        $link = null;
        if (Schema::hasTable('am_link')) {
            $q = DB::table('am_link')->where('object_id', $objectId);
            if ($job && ! empty($job->am_uuid)) {
                $q->where('transfer_uuid', $job->am_uuid);
            }
            $link = $q->orderByDesc('id')->first();
        }

        return response()->json([
            'object_id' => $objectId,
            'job'       => $job,
            'link'      => $link,
            'status'    => $job->status ?? 'none',
        ]);
    }
}
