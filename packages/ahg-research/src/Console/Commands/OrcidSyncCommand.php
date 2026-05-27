<?php

/**
 * OrcidSyncCommand — pull ORCID Works for all linked researchers.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgResearch\Console\Commands;

use AhgResearch\Services\OrcidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrcidSyncCommand extends Command
{
    protected $signature = 'ahg:orcid-sync
        {--limit=500 : Max researchers to process per run}
        {--researcher= : Sync a single researcher by id}
        {--force : Re-sync even if synced within the last 24h}
        {--json : Output results as JSON}';

    protected $description = 'Pull ORCID Works for researchers who have linked their ORCID iD';

    public function handle(OrcidService $orcid): int
    {
        if (! $orcid->isConfigured()) {
            $this->warn('ORCID not configured (ORCID_CLIENT_ID / ORCID_CLIENT_SECRET / ORCID_REDIRECT_URI not set). Skipping.');
            return self::SUCCESS;
        }

        $single = $this->option('researcher')
            ? [(int) $this->option('researcher')]
            : null;

        $force = $this->option('force');

        if ($single !== null) {
            $links = DB::table('researcher_orcid_link')
                ->whereIn('researcher_id', $single)
                ->get();
        } else {
            $limit = (int) $this->option('limit');
            $q = DB::table('researcher_orcid_link')
                ->join('research_researcher as r', 'researcher_orcid_link.researcher_id', '=', 'r.id')
                ->where('r.status', 'approved');

            if (! $force) {
                $q->where(function ($inner) {
                    $inner->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<=', DB::raw("DATE_SUB(NOW(), INTERVAL 24 HOUR)"));
                });
            }

            $links = $q->limit($limit)->get();
        }

        if ($links->isEmpty()) {
            $this->info('No researchers to sync' . ($single ? " (id={$single[0]})" : ''));
            return self::SUCCESS;
        }

        $this->info("Syncing {$links->count()} researcher(s)...");
        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];

        foreach ($links as $link) {
            $rid = (int) $link->researcher_id;
            try {
                $data = $orcid->pullWorks($rid);
                $count = $data['group'] ?? [];
                $titles = count($data['group'] ?? []);
                $totalWorks = 0;
                foreach ($data['group'] ?? [] as $group) {
                    $totalWorks += count($group['work-summary'] ?? []);
                }
                $results[] = [
                    'researcher_id' => $rid,
                    'status' => 'ok',
                    'groups' => $titles,
                    'total_works' => $totalWorks,
                ];
                $this->line("  [ok] researcher {$rid}: {$totalWorks} works across {$titles} groups");
                $ok++;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $results[] = ['researcher_id' => $rid, 'status' => 'fail', 'error' => $msg];
                $this->warn("  [fail] researcher {$rid}: {$msg}");
                $failed++;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $ok,
                'failed' => $failed,
                'skipped' => $skipped,
                'results' => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info("summary: ok={$ok} failed={$failed}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}