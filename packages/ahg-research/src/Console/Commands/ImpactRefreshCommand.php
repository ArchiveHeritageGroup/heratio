<?php

/**
 * ImpactRefreshCommand - Heratio ahg-research
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

use AhgResearch\Services\ImpactTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1241 - Research OS #19 (moonshot 25): Impact Tracking.
 *
 * Poll the PUBLIC bibliographic APIs for the downstream impact (citations,
 * mentions, dataset reuse) of every project's PUBLISHED outputs and insert any
 * new impact signals.
 *
 * Sources OpenAlex (https://api.openalex.org) and Crossref Event Data
 * (https://api.eventdata.crossref.org) DIRECTLY over the Http client - these are
 * PUBLIC bibliographic services, NOT AI services, so they never route through
 * the AHG AI gateway. The whole run is resilient: every project and every output
 * is wrapped so one slow or failing API call simply yields no new signals and
 * the sweep continues. Scheduled daily; also runnable by hand.
 */
class ImpactRefreshCommand extends Command
{
    protected $signature = 'ahg:research-impact-refresh
        {--project= : Refresh a single project by id}
        {--limit=500 : Max projects to scan per run}
        {--json : Output the run summary as JSON}';

    protected $description = 'Poll OpenAlex/Crossref Event Data for citations, mentions and dataset reuse of each project\'s published outputs (Impact Tracking)';

    public function handle(ImpactTrackingService $service): int
    {
        // Resilient bootstrap: if the tables are not present yet, exit cleanly.
        try {
            if (! Schema::hasTable(ImpactTrackingService::SIGNAL_TABLE)
                || ! Schema::hasTable(ImpactTrackingService::SUBMISSION_TABLE)
                || ! Schema::hasTable('research_project')) {
                $this->warn('Impact-tracking tables not present yet; nothing to do.');
                return self::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->warn('DB not ready; skipping impact refresh.');
            return self::SUCCESS;
        }

        $projectIds = $this->resolveProjects((int) $this->option('limit'));

        if ($projectIds === []) {
            $this->info('No projects with published outputs to scan.');
            return self::SUCCESS;
        }

        $this->info('Refreshing impact for ' . count($projectIds) . ' project(s)...');

        $totalSignals = 0;
        $totalOutputs = 0;
        $totalErrors  = 0;
        $results      = [];

        foreach ($projectIds as $pid) {
            try {
                $summary = $service->scanProject($pid);
                $totalSignals += $summary['signals'];
                $totalOutputs += $summary['outputs'];
                $totalErrors  += $summary['errors'];
                $results[] = ['project_id' => $pid] + $summary;
                $this->line(sprintf(
                    '  [project %d] outputs=%d new-signals=%d errors=%d',
                    $pid,
                    $summary['outputs'],
                    $summary['signals'],
                    $summary['errors']
                ));
            } catch (\Throwable $e) {
                // A whole-project failure must never abort the sweep.
                $totalErrors++;
                $results[] = ['project_id' => $pid, 'outputs' => 0, 'signals' => 0, 'errors' => 1, 'error' => $e->getMessage()];
                $this->warn("  [project {$pid}] failed: " . $e->getMessage());
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'projects'    => count($projectIds),
                'outputs'     => $totalOutputs,
                'new_signals' => $totalSignals,
                'errors'      => $totalErrors,
                'results'     => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('summary: projects=' . count($projectIds) . " new-signals={$totalSignals} errors={$totalErrors}");
        }

        return self::SUCCESS;
    }

    /**
     * The set of project ids to scan: a single --project, otherwise every
     * project that has at least one PUBLISHED submission carrying a DOI. Never
     * throws.
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
            if (! Schema::hasColumn(ImpactTrackingService::SUBMISSION_TABLE, 'doi')
                || ! Schema::hasColumn(ImpactTrackingService::SUBMISSION_TABLE, 'status')
                || ! Schema::hasColumn(ImpactTrackingService::SUBMISSION_TABLE, 'project_id')) {
                return [];
            }

            return DB::table(ImpactTrackingService::SUBMISSION_TABLE)
                ->select('project_id')
                ->whereIn('status', ['published', 'accepted'])
                ->whereNotNull('doi')
                ->where('doi', '<>', '')
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
