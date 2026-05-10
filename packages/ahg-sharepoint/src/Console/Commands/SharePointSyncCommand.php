<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Jobs\IngestSharePointEventJob;
use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Services\GraphClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delta-poll one or all ingest-enabled drives.
 *
 * @phase 1
 */
class SharePointSyncCommand extends Command
{
    protected $signature = 'sharepoint:sync {--drive=} {--full} {--limit=0}';
    protected $description = 'Delta-poll one or all ingest-enabled SharePoint drives';

    public function handle(GraphClientService $graph, SharePointDriveRepository $drives): int
    {
        $driveId = (int) $this->option('drive');
        $full = $this->option('full');
        $limit = (int) $this->option('limit');

        $driveRows = $driveId > 0
            ? array_filter([$drives->find($driveId)])
            : $drives->ingestEnabled();

        foreach ($driveRows as $drive) {
            $this->info("sync drive {$drive->id} ({$drive->site_title} / {$drive->drive_name})");
            try {
                $items = $this->syncDrive($graph, $drive, $full, $limit);
                $this->info("  -> {$items} item(s) processed");
            } catch (\Throwable $e) {
                DB::table('sharepoint_sync_state')->updateOrInsert(
                    ['drive_id' => (int) $drive->id],
                    ['last_status' => 'error', 'last_error' => substr($e->getMessage(), 0, 65000), 'last_run_at' => now()],
                );
                $this->error("  -> ERROR: " . $e->getMessage());
            }
        }
        return self::SUCCESS;
    }

    private function syncDrive(GraphClientService $graph, object $drive, bool $full, int $limit): int
    {
        $tenantId = (int) $drive->tenant_id;
        $stateRow = DB::table('sharepoint_sync_state')->where('drive_id', $drive->id)->first();
        $deltaLink = (!$full && $stateRow !== null) ? $stateRow->delta_link : null;

        DB::table('sharepoint_sync_state')->updateOrInsert(
            ['drive_id' => (int) $drive->id],
            ['last_status' => 'in_progress', 'last_run_at' => now()],
        );

        $processed = 0;
        $nextLink = $deltaLink;
        $resp = null;

        do {
            if ($nextLink === null || strpos($nextLink, 'http') !== 0) {
                $resp = $graph->get($tenantId, "/sites/{$drive->site_id}/drives/{$drive->drive_id}/root/delta");
            } else {
                $resp = $graph->get($tenantId, $nextLink);
            }

            foreach (($resp['value'] ?? []) as $item) {
                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }
                $this->createSyntheticEvent($drive, $item);
                $processed++;
            }

            $nextLink = $resp['@odata.nextLink'] ?? null;
        } while ($nextLink !== null);

        $finalDeltaLink = $resp['@odata.deltaLink'] ?? $deltaLink;

        DB::table('sharepoint_sync_state')->updateOrInsert(
            ['drive_id' => (int) $drive->id],
            [
                'delta_link' => $finalDeltaLink,
                'last_status' => 'ok',
                'last_error' => null,
                'last_run_at' => now(),
                'items_processed' => DB::raw('items_processed + ' . $processed),
            ],
        );
        return $processed;
    }

    private function createSyntheticEvent(object $drive, array $item): void
    {
        $eventId = (int) DB::table('sharepoint_event')->insertGetId([
            'subscription_id' => 0,
            'drive_id' => (int) $drive->id,
            'sp_item_id' => $item['id'] ?? null,
            'sp_etag' => $item['eTag'] ?? null,
            'change_type' => 'updated',
            'raw_payload' => json_encode(['source' => 'sync', 'item' => $item], JSON_UNESCAPED_SLASHES),
            'status' => 'received',
            'received_at' => now(),
        ]);
        IngestSharePointEventJob::dispatch($eventId)->onQueue('integrations');
    }
}
