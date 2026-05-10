<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\SharePointSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Create Graph webhook subscriptions (driveItem + list) for a drive.
 *
 * Mirror of sharepointSubscribeTask.class.php in the AtoM plugin.
 *
 * @phase 2.A
 */
class SharePointSubscribeCommand extends Command
{
    protected $signature = 'sharepoint:subscribe {--drive= : sharepoint_drive.id} {--webhook-url= : Public webhook URL}';
    protected $description = 'Create Graph webhook subscriptions (driveItem + list) for a drive';

    public function handle(SharePointSubscriptionService $svc): int
    {
        $driveId = (int) $this->option('drive');
        if ($driveId <= 0) {
            $this->error('--drive=<id> required');
            return self::INVALID;
        }
        $webhookUrl = $this->option('webhook-url') ?: $this->resolveWebhookUrl();
        if ($webhookUrl === null) {
            $this->error('No webhook URL configured. Pass --webhook-url=<url> or set ahg_settings sharepoint.webhook_public_url.');
            return self::INVALID;
        }

        $result = $svc->subscribeDrive($driveId, $webhookUrl);
        $this->info("Subscribed drive {$driveId}: driveItem sub={$result['drive_item']}, list sub={$result['list']}");
        return self::SUCCESS;
    }

    private function resolveWebhookUrl(): ?string
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint')
            ->where('setting_key', 'webhook_public_url')
            ->first();
        return $row->setting_value ?? null;
    }
}
