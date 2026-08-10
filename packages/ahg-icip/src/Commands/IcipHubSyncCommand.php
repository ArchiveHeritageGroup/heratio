<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgIcip\Commands;

use AhgIcip\Services\LocalContextsHubService;
use Illuminate\Console\Command;

/**
 * Sync configured Local Contexts Hub project(s) - their applied TK/BC Labels
 * + Notices - into Heratio (icip_hub_project). Runs on a schedule (registered
 * disabled-by-default in CronSchedulerService) and can be run ad hoc.
 *
 * No-ops cleanly when the integration is disabled or no project ids /
 * credentials are configured, so it is safe on every instance.
 */
class IcipHubSyncCommand extends Command
{
    protected $signature = 'ahg:icip-hub-sync
        {--project= : Sync only this Hub project unique_id (else every configured project)}';

    protected $description = 'Sync configured Local Contexts Hub project(s) (labels + notices) into Heratio. No-op when disabled.';

    public function handle(LocalContextsHubService $hub): int
    {
        if (! $hub->isEnabled()) {
            $this->info('Local Contexts Hub integration is disabled (icip_config.local_contexts_hub_enabled != 1) - nothing to sync.');

            return self::SUCCESS;
        }

        $one = trim((string) $this->option('project'));
        $results = $one !== '' ? [$hub->syncProject($one)] : $hub->syncAll();

        if (empty($results)) {
            $this->warn('No Hub project ids configured (icip_config.local_contexts_project_ids). Nothing to sync.');

            return self::SUCCESS;
        }

        $allOk = true;
        foreach ($results as $r) {
            if (! empty($r['ok'])) {
                $this->info(sprintf(
                    'Synced project %s: %d label(s), %d notice(s).',
                    $r['project_id'],
                    $r['labels'] ?? 0,
                    $r['notices'] ?? 0
                ));
            } else {
                $allOk = false;
                $this->error(sprintf(
                    'Project %s not synced: %s',
                    $r['project_id'] ?? '?',
                    $r['error'] ?? 'unknown error'
                ));
            }
        }

        // Non-zero only when a project was configured but failed (e.g. bad
        // credentials / Hub unreachable) so cron surfaces a real problem; the
        // app itself keeps using the local catalog fallback regardless.
        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
