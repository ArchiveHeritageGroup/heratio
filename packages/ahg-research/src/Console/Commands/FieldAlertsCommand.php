<?php

/**
 * FieldAlertsCommand - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Console\Commands;

use AhgResearch\Services\FieldAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1235 - Research OS Stage 3: poll the public scholarly APIs for
 * retractions / updates / new-related work on every project's cited DOIs and
 * insert any new Living Field Alerts.
 *
 * Sources Crossref (https://api.crossref.org) and OpenAlex
 * (https://api.openalex.org) DIRECTLY over the Http client - these are PUBLIC
 * bibliographic services, NOT AI services, so they never route through the AHG
 * AI gateway. The whole run is resilient: every project and every watch is
 * wrapped so one slow or failing API call simply yields no new alerts and the
 * sweep continues. Scheduled daily; also runnable by hand.
 */
class FieldAlertsCommand extends Command
{
    protected $signature = 'ahg:research-field-alerts
        {--project= : Scan a single project by id}
        {--limit=500 : Max projects to scan per run}
        {--json : Output the run summary as JSON}';

    protected $description = 'Poll Crossref/OpenAlex for retractions, updates and new-related work on each project\'s cited DOIs (Living Field Alerts)';

    public function handle(FieldAlertService $service): int
    {
        // Resilient bootstrap: if the tables are not present yet, exit cleanly.
        try {
            if (! Schema::hasTable(FieldAlertService::WATCH_TABLE) || ! Schema::hasTable('research_project')) {
                $this->warn('Field-alert tables not present yet; nothing to do.');
                return self::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->warn('DB not ready; skipping field-alert sweep.');
            return self::SUCCESS;
        }

        $projectIds = $this->resolveProjects((int) $this->option('limit'));

        if ($projectIds === []) {
            $this->info('No projects to scan.');
            return self::SUCCESS;
        }

        $this->info('Scanning ' . count($projectIds) . ' project(s) for field alerts...');

        $totalAlerts  = 0;
        $totalWatches = 0;
        $totalErrors  = 0;
        $results      = [];

        foreach ($projectIds as $pid) {
            try {
                $summary = $service->scanProject($pid);
                $totalAlerts  += $summary['alerts'];
                $totalWatches += $summary['watches'];
                $totalErrors  += $summary['errors'];
                $results[] = ['project_id' => $pid] + $summary;
                $this->line(sprintf(
                    '  [project %d] watches=%d new-alerts=%d errors=%d',
                    $pid,
                    $summary['watches'],
                    $summary['alerts'],
                    $summary['errors']
                ));
            } catch (\Throwable $e) {
                // A whole-project failure must never abort the sweep.
                $totalErrors++;
                $results[] = ['project_id' => $pid, 'watches' => 0, 'alerts' => 0, 'errors' => 1, 'error' => $e->getMessage()];
                $this->warn("  [project {$pid}] failed: " . $e->getMessage());
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'projects'   => count($projectIds),
                'watches'    => $totalWatches,
                'new_alerts' => $totalAlerts,
                'errors'     => $totalErrors,
                'results'    => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info("summary: projects=" . count($projectIds) . " new-alerts={$totalAlerts} errors={$totalErrors}");
        }

        return self::SUCCESS;
    }

    /**
     * The set of project ids to scan: a single --project, otherwise every
     * project that has at least one watch row. Never throws.
     *
     * @return array<int,int>
     */
    private function resolveProjects(int $limit): array
    {
        $single = $this->option('project');
        if ($single !== null && $single !== '') {
            return [(int) $single];
        }

        try {
            return DB::table(FieldAlertService::WATCH_TABLE)
                ->select('project_id')
                ->distinct()
                ->orderBy('project_id')
                ->limit(max(1, $limit))
                ->pluck('project_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
