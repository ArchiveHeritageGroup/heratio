<?php

/**
 * TransferServiceTest - Direction-2 transfer + poll tests (mocked Dashboard).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Tests;

use AhgArchivematica\Jobs\PollArchivematicaJobs;
use AhgArchivematica\Services\ArchivematicaDashboardClient;
use AhgArchivematica\Services\TransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Exercises the Heratio -> Archivematica (Direction 2) path with the Dashboard
 * API fully mocked - no network, no live AM.
 *
 * Coverage:
 *   - TransferService::send() creates an am_job (direction='to_am') and, after
 *     start+approve, stores the returned transfer UUID and flips to 'processing'.
 *   - PollArchivematicaJobs::pollJob() advances a processing job to 'complete'
 *     once transfer + ingest report COMPLETE, writing SIP/AIP onto am_link.
 *   - pollJob() keeps a still-running transfer in 'processing' and stamps
 *     last_polled_at.
 *
 * Skips gracefully when the am_job / am_link tables aren't installed (the
 * foundation package hasn't run its install.sql in this environment yet).
 */
class TransferServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $objectId;

    private string $stagingDir = '';

    private string $uploadsDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('am_job') || ! Schema::hasTable('am_link')) {
            $this->markTestSkipped('am_job / am_link tables not present in this install.');
        }

        // Deterministic config so the service doesn't depend on ahg_settings.
        config()->set('archivematica.am_default_pipeline_uuid', 'PIPELINE-UUID');
        config()->set('archivematica.am_transfer_source_path', '/var/archivematica/source');

        // The parent record (FK-valid, so digital_object below can reference it).
        $this->objectId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // send() now stages the record's digital objects into a local mount
        // (am_transfer_staging_path) before starting the transfer, so give the
        // test a real staging dir, uploads root, and one digital object on disk.
        $this->stagingDir = sys_get_temp_dir().'/am-stage-'.$this->objectId;
        $this->uploadsDir = sys_get_temp_dir().'/am-uploads-'.$this->objectId;
        @mkdir($this->stagingDir, 0775, true);
        @mkdir($this->uploadsDir.'/r', 0775, true);
        file_put_contents($this->uploadsDir.'/r/sample.txt', 'demo digital object');
        config()->set('archivematica.am_transfer_staging_path', $this->stagingDir);
        config()->set('heratio.uploads_path', $this->uploadsDir);

        $doId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('digital_object')->insert([
            'id'        => $doId,
            'object_id' => $this->objectId,
            'name'      => 'sample.txt',
            'path'      => 'r/',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        foreach ([$this->stagingDir, $this->uploadsDir] as $dir) {
            if ($dir !== '' && is_dir($dir)) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $f) {
                    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
                }
                @rmdir($dir);
            }
        }
        parent::tearDown();
    }

    public function test_send_creates_job_and_stores_transfer_uuid(): void
    {
        $client = Mockery::mock(ArchivematicaDashboardClient::class);
        $client->shouldReceive('isConfigured')->andReturnTrue();
        $client->shouldReceive('startTransfer')->once()->andReturn([
            'message' => 'Copy successful.',
            'path'    => '/var/archivematica/source/heratio-io-' . $this->objectId . '/',
        ]);
        $client->shouldReceive('approveTransfer')->once()->andReturn([
            'message' => 'approved',
            'uuid'    => 'TRANSFER-UUID-123',
        ]);

        $service = new TransferService($client);
        $jobId = $service->send($this->objectId, '/var/archivematica/source/rec', 'standard');

        $job = DB::table('am_job')->where('id', $jobId)->first();

        $this->assertNotNull($job, 'am_job row should have been created');
        $this->assertSame($this->objectId, (int) $job->object_id);
        $this->assertSame('to_am', $job->direction);
        $this->assertSame('processing', $job->status);
        $this->assertSame('TRANSFER-UUID-123', $job->am_uuid);

        $payload = json_decode($job->payload, true);
        $this->assertSame('TRANSFER-UUID-123', $payload['transfer_uuid'] ?? null);
    }

    public function test_send_marks_job_failed_when_approve_returns_no_uuid(): void
    {
        $client = Mockery::mock(ArchivematicaDashboardClient::class);
        $client->shouldReceive('isConfigured')->andReturnTrue();
        $client->shouldReceive('startTransfer')->once()->andReturn(['path' => '/x/rec/']);
        $client->shouldReceive('approveTransfer')->once()->andReturn(['message' => 'no uuid here']);

        $service = new TransferService($client);

        try {
            $service->send($this->objectId, '/x/rec', 'standard');
            $this->fail('Expected a RuntimeException when no UUID is returned.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $job = DB::table('am_job')
            ->where('object_id', $this->objectId)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('failed', $job->status);
        $this->assertNotEmpty($job->error);
    }

    public function test_poll_advances_processing_job_to_complete(): void
    {
        $jobId = $this->seedProcessingJob('TRANSFER-UUID-999');

        $client = Mockery::mock(ArchivematicaDashboardClient::class);
        $client->shouldReceive('transferStatus')->with('TRANSFER-UUID-999')->andReturn([
            'status'       => 'COMPLETE',
            'microservice' => 'Move to SIP creation',
            'sip_uuid'     => 'SIP-UUID-1',
        ]);
        $client->shouldReceive('ingestStatus')->with('SIP-UUID-1')->andReturn([
            'status'       => 'COMPLETE',
            'microservice' => 'Store AIP',
            'aip_uuid'     => 'AIP-UUID-1',
        ]);

        $job = DB::table('am_job')->where('id', $jobId)->first();
        (new PollArchivematicaJobs())->pollJob($job, $client);

        $updated = DB::table('am_job')->where('id', $jobId)->first();
        $this->assertSame('complete', $updated->status);
        $this->assertNotNull($updated->last_polled_at);

        $link = DB::table('am_link')
            ->where('object_id', $this->objectId)
            ->where('transfer_uuid', 'TRANSFER-UUID-999')
            ->first();
        $this->assertNotNull($link, 'am_link row should have been written');
        $this->assertSame('SIP-UUID-1', $link->sip_uuid);
        $this->assertSame('AIP-UUID-1', $link->aip_uuid);
        $this->assertSame('complete', $link->status);
    }

    public function test_poll_keeps_in_flight_transfer_processing(): void
    {
        $jobId = $this->seedProcessingJob('TRANSFER-UUID-INFLIGHT');

        $client = Mockery::mock(ArchivematicaDashboardClient::class);
        $client->shouldReceive('transferStatus')->andReturn([
            'status'       => 'PROCESSING',
            'microservice' => 'Scan for viruses',
        ]);
        // ingestStatus must never be called while the transfer is still running.
        $client->shouldNotReceive('ingestStatus');

        $job = DB::table('am_job')->where('id', $jobId)->first();
        (new PollArchivematicaJobs())->pollJob($job, $client);

        $updated = DB::table('am_job')->where('id', $jobId)->first();
        $this->assertSame('processing', $updated->status);
        $this->assertSame('Scan for viruses', $updated->microservice);
        $this->assertNotNull($updated->last_polled_at);
    }

    private function seedProcessingJob(string $transferUuid): int
    {
        $now = now();

        return (int) DB::table('am_job')->insertGetId([
            'object_id'      => $this->objectId,
            'direction'      => 'to_am',
            'status'         => 'processing',
            'am_uuid'        => $transferUuid,
            'microservice'   => null,
            'last_polled_at' => null,
            'error'          => null,
            'payload'        => json_encode([
                'transfer_uuid' => $transferUuid,
                'pipeline_uuid' => 'PIPELINE-UUID',
            ]),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }
}
