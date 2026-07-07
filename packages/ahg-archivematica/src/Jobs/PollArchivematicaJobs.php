<?php

/**
 * PollArchivematicaJobs - advances in-flight Heratio -> Archivematica transfers.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Jobs;

use AhgArchivematica\Services\ArchivematicaDashboardClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sweeps every processing am_job (direction='to_am') and moves it forward by
 * polling the Archivematica Dashboard. When the transfer completes we learn
 * the SIP UUID; when ingest completes we learn the AIP UUID. Both are written
 * onto the matching am_link row (created/linked on first sight), and the job
 * is closed as 'complete' (or 'failed' with an error).
 *
 * Archivematica status vocabulary (transfer + ingest):
 *   PROCESSING  - still running a microservice
 *   USER_INPUT  - waiting on a manual decision in the AM dashboard
 *   COMPLETE    - stage finished
 *   FAILED      - a microservice failed
 *   REJECTED    - the transfer/SIP was rejected
 *
 * The Dashboard client is injected into handle() so the poller is fully
 * mockable in tests. pollJob() is public so a single row can be exercised in
 * isolation.
 */
class PollArchivematicaJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Statuses that mean "keep polling". */
    private const IN_FLIGHT = ['PROCESSING', 'USER_INPUT'];

    /** Statuses that mean the stage failed for good. */
    private const TERMINAL_FAILURE = ['FAILED', 'REJECTED'];

    /**
     * Poll every processing to_am job. Safe to run when the tables are absent
     * (fresh installs) - it simply no-ops.
     */
    public function handle(ArchivematicaDashboardClient $client): void
    {
        if (! Schema::hasTable('am_job')) {
            Log::warning('[archivematica] am_job table missing - poll skipped.');
            return;
        }

        $jobs = DB::table('am_job')
            ->where('direction', 'to_am')
            ->where('status', 'processing')
            ->orderBy('id')
            ->get();

        foreach ($jobs as $job) {
            try {
                $this->pollJob($job, $client);
            } catch (\Throwable $e) {
                // A transient poll error shouldn't kill the whole sweep; record
                // it against the row and move on. The job stays 'processing'
                // so the next sweep retries.
                Log::warning('[archivematica] poll error on job ' . $job->id . ': ' . $e->getMessage());
                DB::table('am_job')->where('id', $job->id)->update([
                    'error'          => mb_substr($e->getMessage(), 0, 2000),
                    'last_polled_at' => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    /**
     * Advance a single am_job by one poll cycle.
     *
     * @param object $job a row from am_job
     */
    public function pollJob(object $job, ArchivematicaDashboardClient $client): void
    {
        $transferUuid = (string) ($job->am_uuid ?? '');
        if ($transferUuid === '') {
            $this->fail((int) $job->id, 'Processing job has no transfer UUID.');
            return;
        }

        $payload = $this->decodePayload($job->payload ?? null);

        // --- Transfer stage -------------------------------------------------
        $transfer = $client->transferStatus($transferUuid);
        $transferStatus = strtoupper((string) ($transfer['status'] ?? ''));
        $microservice = (string) ($transfer['microservice'] ?? '');
        $sipUuid = (string) ($transfer['sip_uuid'] ?? '');

        if (in_array($transferStatus, self::TERMINAL_FAILURE, true)) {
            $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid ?: null, null, 'failed', $payload);
            $this->fail((int) $job->id, "Transfer {$transferStatus} at microservice: {$microservice}");
            return;
        }

        if ($transferStatus !== 'COMPLETE') {
            // Still transferring (or waiting on user input).
            $this->touch((int) $job->id, $microservice, $payload);
            $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid ?: null, null, 'processing', $payload);
            return;
        }

        // Transfer complete. Record the SIP UUID and roll into ingest polling.
        if ($sipUuid !== '') {
            $payload['sip_uuid'] = $sipUuid;
        }
        $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid ?: null, null, 'processing', $payload);

        if ($sipUuid === '') {
            // Transfer complete but no SIP handle yet (backlog / brief lag) -
            // keep the job open and retry next sweep.
            $this->touch((int) $job->id, $microservice ?: 'transfer-complete', $payload);
            return;
        }

        // --- Ingest stage ---------------------------------------------------
        $ingest = $client->ingestStatus($sipUuid);
        $ingestStatus = strtoupper((string) ($ingest['status'] ?? ''));
        $ingestMicroservice = (string) ($ingest['microservice'] ?? '');
        // The ingest UUID is the SIP; the stored AIP UUID appears as uuid/aip_uuid.
        $aipUuid = (string) ($ingest['aip_uuid'] ?? $ingest['uuid'] ?? '');

        if (in_array($ingestStatus, self::TERMINAL_FAILURE, true)) {
            $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid, null, 'failed', $payload);
            $this->fail((int) $job->id, "Ingest {$ingestStatus} at microservice: {$ingestMicroservice}");
            return;
        }

        if ($ingestStatus !== 'COMPLETE') {
            $this->touch((int) $job->id, $ingestMicroservice ?: 'ingest', $payload);
            $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid, null, 'processing', $payload);
            return;
        }

        // Ingest complete - AIP stored. Close the job.
        if ($aipUuid !== '') {
            $payload['aip_uuid'] = $aipUuid;
        }
        $this->writeLink((int) $job->object_id, $transferUuid, $sipUuid, $aipUuid ?: null, 'complete', $payload);
        $this->complete((int) $job->id, $ingestMicroservice ?: 'ingest-complete', $payload);

        Log::info('[archivematica] transfer complete', [
            'job_id'        => $job->id,
            'object_id'     => $job->object_id,
            'transfer_uuid' => $transferUuid,
            'sip_uuid'      => $sipUuid,
            'aip_uuid'      => $aipUuid,
        ]);
    }

    /**
     * Update microservice + last_polled_at while a job stays processing.
     *
     * @param array<string,mixed> $payload
     */
    private function touch(int $jobId, string $microservice, array $payload): void
    {
        DB::table('am_job')->where('id', $jobId)->update([
            'status'         => 'processing',
            'microservice'   => $microservice !== '' ? $microservice : null,
            'payload'        => json_encode($payload),
            'last_polled_at' => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Close a job as complete.
     *
     * @param array<string,mixed> $payload
     */
    private function complete(int $jobId, string $microservice, array $payload): void
    {
        DB::table('am_job')->where('id', $jobId)->update([
            'status'         => 'complete',
            'microservice'   => $microservice !== '' ? $microservice : null,
            'error'          => null,
            'payload'        => json_encode($payload),
            'last_polled_at' => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Close a job as failed with an error.
     */
    private function fail(int $jobId, string $error): void
    {
        DB::table('am_job')->where('id', $jobId)->update([
            'status'         => 'failed',
            'error'          => mb_substr($error, 0, 2000),
            'last_polled_at' => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Create or update the am_link row that maps a Heratio object to its AM
     * package UUIDs. Keyed on (object_id, transfer_uuid). Only writes the
     * SIP/AIP columns when we have a value, so a later poll can fill them in.
     *
     * @param array<string,mixed> $payload used only for am_pipeline_uuid
     */
    private function writeLink(
        int $objectId,
        string $transferUuid,
        ?string $sipUuid,
        ?string $aipUuid,
        string $status,
        array $payload
    ): void {
        if (! Schema::hasTable('am_link')) {
            return;
        }

        $existing = DB::table('am_link')
            ->where('object_id', $objectId)
            ->where('transfer_uuid', $transferUuid)
            ->first();

        $now = now();
        $pipelineUuid = (string) ($payload['pipeline_uuid'] ?? config('archivematica.am_default_pipeline_uuid', ''));

        if ($existing) {
            $update = ['status' => $status, 'updated_at' => $now];
            if ($sipUuid !== null && $sipUuid !== '') {
                $update['sip_uuid'] = $sipUuid;
            }
            if ($aipUuid !== null && $aipUuid !== '') {
                $update['aip_uuid'] = $aipUuid;
            }
            DB::table('am_link')->where('id', $existing->id)->update($update);
            return;
        }

        DB::table('am_link')->insert([
            'object_id'        => $objectId,
            'transfer_uuid'    => $transferUuid,
            'sip_uuid'         => $sipUuid ?: null,
            'aip_uuid'         => $aipUuid ?: null,
            'dip_uuid'         => null,
            'status'           => $status,
            'am_pipeline_uuid' => $pipelineUuid !== '' ? $pipelineUuid : null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    /**
     * Decode the am_job.payload JSON to an array (empty on null/garbage).
     *
     * @param mixed $raw
     *
     * @return array<string,mixed>
     */
    private function decodePayload($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
