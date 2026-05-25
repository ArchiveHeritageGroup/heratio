<?php
/**
 * Heratio - EU AI Act Article 9 post-market monitoring sweep.
 *
 * Builds a weekly digest of inference-log activity + open incidents +
 * overdue reviews, and drops it into the workbench notification spool
 * for Johan's bell.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Services\AiRiskService;
use Illuminate\Console\Command;

final class RiskMonitorCommand extends Command
{
    protected $signature = 'ai-compliance:risk-monitor
        {--days=7        : Look-back window in days}
        {--user=johan    : Workbench notification recipient username}
        {--quiet-empty   : Skip notification when there is nothing notable}';

    protected $description = 'Weekly post-market monitoring digest for the EU AI Act Article 9 risk register';

    public function handle(AiRiskService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $digest = $service->postMarketDigest($since);

        $this->line('Post-market monitoring digest');
        $this->line('  Window:           ' . $digest['since']);
        $this->line('  Inferences:       ' . array_sum((array) $digest['inferences']));
        $this->line('  Guardrail events: ' . $digest['guardrail_events']);
        $this->line('  Open incidents:   ' . $digest['open_incidents']);
        $this->line('  Overdue reviews:  ' . $digest['overdue_reviews']);

        $notable = ($digest['open_incidents'] > 0)
                || ($digest['overdue_reviews'] > 0)
                || ($digest['guardrail_events'] > 50);

        if (!$notable && $this->option('quiet-empty')) {
            $this->info('Nothing notable; skipping notification.');
            return self::SUCCESS;
        }

        $this->notifyWorkbench((string) $this->option('user'), $digest);
        $this->info('Digest posted to workbench notification spool.');

        return self::SUCCESS;
    }

    private function notifyWorkbench(string $user, array $digest): void
    {
        $inbox = env('WORKBENCH_NOTIFICATIONS_INBOX', '/var/spool/workbench/notifications');
        if (!is_dir($inbox) || !is_writable($inbox)) {
            $this->warn("Notification spool not writable at {$inbox}; skipping.");
            return;
        }

        $title = sprintf(
            'AI compliance digest: %d incidents, %d overdue reviews',
            $digest['open_incidents'],
            $digest['overdue_reviews'],
        );

        $body = sprintf(
            "Window since %s.\nInferences logged: %d.\nGuardrail decisions: %d.\nOpen incidents: %d.\nReviews overdue: %d.",
            $digest['since'],
            array_sum((array) $digest['inferences']),
            $digest['guardrail_events'],
            $digest['open_incidents'],
            $digest['overdue_reviews'],
        );

        $payload = json_encode([
            'username'     => $user,
            'title'        => $title,
            'message'      => $body,
            'eventType'    => 'compliance',
            'webLink'      => url('/admin/ai-compliance/risk'),
            'deadlineHint' => '2026-08-02 (EU AI Act enforcement)',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $file = $inbox . '/ai-compliance-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.json';
        @file_put_contents($file, (string) $payload);
    }
}
