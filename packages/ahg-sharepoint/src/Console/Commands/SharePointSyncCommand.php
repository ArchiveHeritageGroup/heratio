<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Jobs\IngestSharePointEventJob;
use AhgSharePoint\Mail\SharePointSyncErrorMail;
use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Services\GraphClientService;
use App\Services\EmailSuppressionGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

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
                $this->error('  -> ERROR: '.$e->getMessage());

                // Phase 3 of #674 - surface sync errors to the ops mailbox.
                // Best-effort; never let a notification failure mask the
                // original sync error.
                try {
                    $this->dispatchSyncErrorMail($drive, $e);
                } catch (\Throwable $mailErr) {
                    $this->warn('  -> notification dispatch failed: '.$mailErr->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Dispatch SharePointSyncErrorMail to every configured ops address.
     * Picks recipients from (in order):
     *   - config('ahg.sharepoint.ops_email') / env('SHAREPOINT_OPS_EMAIL')
     *   - ahg_settings.sharepoint_ops_email   (comma-separated)
     *   - ahg_settings.sharepoint_admin_email (single)
     *
     * @phase 3
     */
    private function dispatchSyncErrorMail(object $drive, \Throwable $e): void
    {
        $recipients = $this->resolveOpsRecipients();
        if (empty($recipients)) {
            return;
        }

        $stateRow = DB::table('sharepoint_sync_state')->where('drive_id', (int) $drive->id)->first();
        $context = [
            'connection_name' => trim(($drive->site_title ?? '').' / '.($drive->drive_name ?? '')) ?: 'SharePoint',
            'site_url' => $drive->site_url ?? null,
            'error_kind' => $this->classifyError($e),
            'error_message' => $e->getMessage(),
            'failed_items' => (int) ($stateRow->failed_items ?? 0),
            'last_success_at' => $stateRow->last_success_at ?? null,
            'run_id' => (string) ($stateRow->id ?? ''),
            'dashboard_url' => rtrim((string) config('app.url', ''), '/').'/admin/sharepoint',
        ];

        foreach ($recipients as $email) {
            if (! EmailSuppressionGate::canSend(
                $email,
                SharePointSyncErrorMail::class,
                'SharePoint sync error: '.$context['connection_name']
            )) {
                continue;
            }
            $ctx = $context + [
                'recipient_email' => $email,
                'recipient_name' => null,
                'preferred_locale' => null,
            ];
            Mail::to($email)->queue(new SharePointSyncErrorMail($ctx));
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveOpsRecipients(): array
    {
        $out = [];
        $seen = [];
        $push = function ($value) use (&$out, &$seen) {
            foreach (preg_split('/[,;\s]+/', (string) $value) as $addr) {
                $addr = strtolower(trim((string) $addr));
                if ($addr === '' || isset($seen[$addr])) {
                    continue;
                }
                if (! filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $seen[$addr] = true;
                $out[] = $addr;
            }
        };

        $push((string) (config('ahg.sharepoint.ops_email') ?? env('SHAREPOINT_OPS_EMAIL', '')));

        try {
            if (Schema::hasTable('ahg_settings')) {
                foreach (['sharepoint_ops_email', 'sharepoint_admin_email'] as $key) {
                    $row = DB::table('ahg_settings')->where('setting_key', $key)->first();
                    if ($row && trim((string) $row->setting_value) !== '') {
                        $push($row->setting_value);
                    }
                }
            }
        } catch (\Throwable $e) {
            // settings table missing - ignore
        }

        return $out;
    }

    private function classifyError(\Throwable $e): string
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, '401') || str_contains($msg, 'unauthorized') || str_contains($msg, 'token')) {
            return 'auth';
        }
        if (str_contains($msg, 'timeout') || str_contains($msg, 'connection') || str_contains($msg, 'curl')) {
            return 'network';
        }
        if (str_contains($msg, 'quota') || str_contains($msg, '429') || str_contains($msg, 'throttle')) {
            return 'quota';
        }
        if (str_contains($msg, 'conflict') || str_contains($msg, '409')) {
            return 'conflict';
        }

        return 'other';
    }

    private function syncDrive(GraphClientService $graph, object $drive, bool $full, int $limit): int
    {
        $tenantId = (int) $drive->tenant_id;
        $stateRow = DB::table('sharepoint_sync_state')->where('drive_id', $drive->id)->first();
        $deltaLink = (! $full && $stateRow !== null) ? $stateRow->delta_link : null;

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
                'items_processed' => DB::raw('items_processed + '.$processed),
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
