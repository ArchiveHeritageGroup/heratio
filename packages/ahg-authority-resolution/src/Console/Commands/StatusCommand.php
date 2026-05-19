<?php

/**
 * StatusCommand - Console command for Heratio
 *
 * Task 10 (CLI consolidation): operator-facing snapshot of the entire
 * authority-resolution engine state. One-shot read of every workflow table,
 * grouped by the dimensions an operator typically asks about:
 *
 *   - ahg_mention                  -> by state, by entity_type
 *   - ahg_mention_candidate        -> total + avg per mention
 *   - ahg_mention_decision         -> by decision_type
 *   - ahg_mention_park             -> total + new_candidate_available subtotal
 *   - ahg_ner_feedback             -> total + unexported subtotal
 *   - ahg_authority_lookup_cache   -> total + per-source subtotal
 *   - Fuseki named graphs          -> triple count per decisions/field-prov graph
 *
 * Read-only. Cheap. Safe to run at any time. The Fuseki block is best-effort:
 * if AhgRic\Services\SparqlQueryService is not in the container or the call
 * fails, the block prints "(skipped: ...)" rather than aborting the rest.
 *
 * Usage:
 *   php artisan auth-res:status
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

namespace AhgAuthorityResolution\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatusCommand extends Command
{
    protected $signature = 'auth-res:status';

    protected $description = 'Print a read-only snapshot of every authority-resolution workflow table + Fuseki provenance graphs.';

    public function handle(): int
    {
        $this->mentionsByState();
        $this->mentionsByEntityType();
        $this->candidates();
        $this->decisionsByType();
        $this->parkQueue();
        $this->nerFeedback();
        $this->lookupCache();
        $this->fusekiGraphs();
        return self::SUCCESS;
    }

    private function mentionsByState(): void
    {
        $rows = DB::table('ahg_mention')
            ->select('state', DB::raw('COUNT(*) AS c'))
            ->groupBy('state')
            ->orderBy('state')
            ->get();

        $this->line('ahg_mention rows by state:');
        if ($rows->isEmpty()) {
            $this->line('    (none)');
            return;
        }
        foreach ($rows as $r) {
            $this->line(sprintf('    %-20s %d', (string) $r->state . ':', (int) $r->c));
        }
    }

    private function mentionsByEntityType(): void
    {
        $rows = DB::table('ahg_mention')
            ->select('entity_type', DB::raw('COUNT(*) AS c'))
            ->groupBy('entity_type')
            ->orderBy('entity_type')
            ->get();

        $this->line('ahg_mention rows by entity_type:');
        if ($rows->isEmpty()) {
            $this->line('    (none)');
            return;
        }
        foreach ($rows as $r) {
            $this->line(sprintf('    %-10s %d', (string) $r->entity_type . ':', (int) $r->c));
        }
    }

    private function candidates(): void
    {
        $totalCandidates = (int) DB::table('ahg_mention_candidate')->count();
        $distinctMentions = (int) DB::table('ahg_mention_candidate')
            ->distinct()
            ->count('mention_id');
        $avg = $distinctMentions > 0
            ? round($totalCandidates / $distinctMentions, 1)
            : 0.0;
        $this->line(sprintf(
            'ahg_mention_candidate rows: %d (avg %.1f per scored mention)',
            $totalCandidates,
            $avg
        ));
    }

    private function decisionsByType(): void
    {
        $rows = DB::table('ahg_mention_decision')
            ->select('decision_type', DB::raw('COUNT(*) AS c'))
            ->groupBy('decision_type')
            ->orderBy('decision_type')
            ->get();

        $this->line('ahg_mention_decision rows by type:');
        if ($rows->isEmpty()) {
            $this->line('    (none)');
            return;
        }
        foreach ($rows as $r) {
            $this->line(sprintf('    %-20s %d', (string) $r->decision_type . ':', (int) $r->c));
        }
    }

    private function parkQueue(): void
    {
        $total = (int) DB::table('ahg_mention_park')->count();
        $newCandidate = (int) DB::table('ahg_mention_park')
            ->where('new_candidate_available', 1)
            ->count();
        $this->line(sprintf(
            'ahg_mention_park rows: %d (new_candidate_available: %d)',
            $total,
            $newCandidate
        ));
    }

    private function nerFeedback(): void
    {
        $total = (int) DB::table('ahg_ner_feedback')->count();
        $unexported = (int) DB::table('ahg_ner_feedback')
            ->where('training_exported', 0)
            ->count();
        $this->line(sprintf(
            'ahg_ner_feedback rows: %d (unexported: %d)',
            $total,
            $unexported
        ));
    }

    private function lookupCache(): void
    {
        $total = (int) DB::table('ahg_authority_lookup_cache')->count();
        $bySource = DB::table('ahg_authority_lookup_cache')
            ->select('source', DB::raw('COUNT(*) AS c'))
            ->groupBy('source')
            ->orderBy('source')
            ->get();

        if ($bySource->isEmpty()) {
            $this->line('ahg_authority_lookup_cache rows: 0');
            return;
        }

        $parts = [];
        foreach ($bySource as $r) {
            $parts[] = (string) $r->source . '=' . (int) $r->c;
        }
        $this->line(sprintf(
            'ahg_authority_lookup_cache rows: %d (by source: %s)',
            $total,
            implode(', ', $parts)
        ));
    }

    private function fusekiGraphs(): void
    {
        $serviceClass = '\\AhgRic\\Services\\SparqlQueryService';
        if (!class_exists($serviceClass)) {
            $this->line('Fuseki provenance graphs: (skipped: AhgRic\\Services\\SparqlQueryService not installed)');
            return;
        }

        try {
            /** @var object $service */
            $service = app($serviceClass);
        } catch (\Throwable $e) {
            $this->line('Fuseki provenance graphs: (skipped: ' . $e->getMessage() . ')');
            return;
        }

        $graphs = [
            'urn:heratio:auth-res:graph:decisions',
            'urn:atom:auth-res:graph:decisions',
            'urn:heratio:auth-res:graph:field-provenance',
            'urn:atom:auth-res:graph:field-provenance',
        ];

        $this->line('Fuseki provenance graphs:');
        foreach ($graphs as $g) {
            $sparql = "SELECT (COUNT(*) AS ?c) WHERE { GRAPH <{$g}> { ?s ?p ?o } }";
            try {
                $result = $service->executeQuery($sparql, false);
                $bindings = $result['bindings'] ?? [];
                $c = (int) ($bindings[0]['c']['value'] ?? 0);
                $this->line(sprintf('    %s : %d triples', $g, $c));
            } catch (\Throwable $e) {
                $this->line(sprintf('    %s : (error: %s)', $g, $e->getMessage()));
            }
        }
    }
}
