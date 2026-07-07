<?php

/**
 * TransferService - drives Heratio -> Archivematica transfers (Direction 2).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Kicks off an Archivematica transfer for a Heratio information_object and
 * records it as an am_job (direction='to_am') so PollArchivematicaJobs can
 * follow it to completion.
 *
 * Flow (send):
 *   1. Resolve a source path under config('archivematica.am_transfer_source_path')
 *      and the default pipeline UUID.
 *   2. Create an am_job row (status='pending').
 *   3. start_transfer -> approve via the Dashboard client to obtain the
 *      transfer UUID.
 *   4. Store the transfer UUID on the job (am_uuid + payload.transfer_uuid),
 *      flip status to 'processing'.
 *   5. On any failure, mark the job 'failed' with the error and rethrow so
 *      the controller can surface it.
 *
 * The service takes the Dashboard client by constructor injection so tests can
 * mock the network layer entirely.
 */
class TransferService
{
    private ArchivematicaDashboardClient $client;

    public function __construct(ArchivematicaDashboardClient $client)
    {
        $this->client = $client;
    }

    /**
     * Send an information_object to Archivematica. Returns the created/updated
     * am_job id.
     *
     * @param int         $objectId    information_object.id
     * @param string|null $sourcePath  explicit source path; when null it is
     *                                 derived from am_transfer_source_path + the
     *                                 record slug.
     * @param string      $type        Archivematica transfer type.
     *
     * @throws RuntimeException when the client is not configured or the
     *         Dashboard rejects the transfer.
     */
    public function send(int $objectId, ?string $sourcePath = null, string $type = 'standard'): int
    {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('Archivematica Dashboard is not configured (URL / credentials missing).');
        }

        $pipelineUuid = (string) config('archivematica.am_default_pipeline_uuid', '');
        $sourcePath = $sourcePath ?: $this->resolveSourcePath($objectId);
        $transferName = $this->transferName($objectId);

        // 1. Pending job row first, so a failure mid-flight is still auditable.
        $jobId = $this->createJob($objectId, [
            'transfer_name' => $transferName,
            'source_path'   => $sourcePath,
            'pipeline_uuid' => $pipelineUuid,
            'type'          => $type,
        ]);

        try {
            // 2. Start the transfer in the watched dir.
            $started = $this->client->startTransfer(
                $transferName,
                $type,
                [$sourcePath],
                $pipelineUuid
            );

            // The approve step needs the directory name Archivematica created.
            // start_transfer returns a `path`; its basename is the directory.
            $directory = $this->directoryFromStartResponse($started, $transferName);

            // 3. Approve to obtain the transfer UUID.
            $approved = $this->client->approveTransfer($type, $directory);
            $transferUuid = (string) ($approved['uuid'] ?? '');

            if ($transferUuid === '') {
                throw new RuntimeException(
                    'Archivematica approved the transfer but returned no UUID: '
                    . json_encode($approved)
                );
            }

            // 4. Persist the UUID + move to processing.
            $this->markProcessing($jobId, $transferUuid, [
                'transfer_name' => $transferName,
                'source_path'   => $sourcePath,
                'pipeline_uuid' => $pipelineUuid,
                'type'          => $type,
                'transfer_uuid' => $transferUuid,
                'start_response' => $started,
            ]);

            Log::info('[archivematica] transfer started', [
                'object_id'     => $objectId,
                'job_id'        => $jobId,
                'transfer_uuid' => $transferUuid,
            ]);

            return $jobId;
        } catch (\Throwable $e) {
            $this->markFailed($jobId, $e->getMessage());
            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Insert a pending am_job row. Returns its id.
     *
     * @param array<string,mixed> $payload
     */
    private function createJob(int $objectId, array $payload): int
    {
        $now = now();

        return (int) DB::table('am_job')->insertGetId([
            'object_id'  => $objectId,
            'direction'  => 'to_am',
            'status'     => 'pending',
            'am_uuid'    => null,
            'microservice' => null,
            'last_polled_at' => null,
            'error'      => null,
            'payload'    => json_encode($payload),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Flip a job to 'processing' and store the transfer UUID.
     *
     * @param array<string,mixed> $payload
     */
    private function markProcessing(int $jobId, string $transferUuid, array $payload): void
    {
        DB::table('am_job')->where('id', $jobId)->update([
            'status'     => 'processing',
            'am_uuid'    => $transferUuid,
            'payload'    => json_encode($payload),
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark a job failed with an error string.
     */
    private function markFailed(int $jobId, string $error): void
    {
        DB::table('am_job')->where('id', $jobId)->update([
            'status'     => 'failed',
            'error'      => mb_substr($error, 0, 2000),
            'updated_at' => now(),
        ]);
    }

    /**
     * Derive the transfer source path for a record. The base directory comes
     * from config; the per-record leaf is the record slug (falling back to the
     * object id) so each transfer lands in its own directory.
     */
    private function resolveSourcePath(int $objectId): string
    {
        $base = rtrim((string) config('archivematica.am_transfer_source_path', ''), '/');
        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        $leaf = $slug ?: ('io-' . $objectId);

        return $base !== '' ? ($base . '/' . $leaf) : $leaf;
    }

    /**
     * Build a stable, human-readable transfer name for a record.
     */
    private function transferName(int $objectId): string
    {
        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');

        return 'heratio-' . ($slug ?: ('io-' . $objectId));
    }

    /**
     * Extract the watched-dir directory name from a start_transfer response.
     * Archivematica returns a `path` like
     * "/var/archivematica/.../heratio-foo-1234/"; approve() wants its basename.
     * Falls back to the transfer name when `path` is absent.
     *
     * @param array<string,mixed> $started
     */
    private function directoryFromStartResponse(array $started, string $fallback): string
    {
        $path = trim((string) ($started['path'] ?? ''));
        if ($path === '') {
            return $fallback;
        }

        return basename(rtrim($path, '/'));
    }
}
