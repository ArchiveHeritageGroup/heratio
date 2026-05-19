<?php

/**
 * CacheStatsCommand - Console command for Heratio
 *
 * Task 10 (CLI consolidation). Per-source summary of the external authority
 * lookup cache (ahg_authority_lookup_cache). For each source (viaf, wikidata,
 * geonames, tgn, gnd, isni, sagnc) emit:
 *
 *   - row count
 *   - oldest retrieved_at
 *   - newest retrieved_at
 *   - distinct entity_type set
 *
 * Pure SQL aggregation. Used to size the cache before --force purging and to
 * answer "did the GeoNames adapter ever actually get called".
 *
 * Usage:
 *   php artisan auth-res:cache-stats
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

class CacheStatsCommand extends Command
{
    /** All sources we know about; force-listed so a zero-row source still prints. */
    private const KNOWN_SOURCES = ['viaf', 'wikidata', 'geonames', 'tgn', 'gnd', 'isni', 'sagnc'];

    protected $signature = 'auth-res:cache-stats';

    protected $description = 'External lookup cache (ahg_authority_lookup_cache) summary by source.';

    public function handle(): int
    {
        $total = (int) DB::table('ahg_authority_lookup_cache')->count();
        $this->line("ahg_authority_lookup_cache: {$total} rows");

        $rows = DB::table('ahg_authority_lookup_cache')
            ->select(
                'source',
                DB::raw('COUNT(*) AS c'),
                DB::raw('MIN(retrieved_at) AS oldest'),
                DB::raw('MAX(retrieved_at) AS newest'),
                DB::raw('GROUP_CONCAT(DISTINCT entity_type ORDER BY entity_type SEPARATOR \',\') AS types')
            )
            ->groupBy('source')
            ->orderBy('source')
            ->get();

        $byName = [];
        foreach ($rows as $r) {
            $byName[(string) $r->source] = $r;
        }

        // Print known sources first (even if zero), then any unknown extras.
        $printed = [];
        foreach (self::KNOWN_SOURCES as $name) {
            $this->printSource($name, $byName[$name] ?? null);
            $printed[$name] = true;
        }
        foreach ($byName as $name => $row) {
            if (!isset($printed[$name])) {
                $this->printSource($name, $row);
            }
        }

        return self::SUCCESS;
    }

    private function printSource(string $name, ?object $row): void
    {
        if ($row === null || (int) $row->c === 0) {
            $this->line(sprintf('  %-9s 0 entries', $name . ':'));
            return;
        }
        $this->line(sprintf(
            '  %-9s %d entries, oldest %s, newest %s, types %s',
            $name . ':',
            (int) $row->c,
            $this->formatDate((string) ($row->oldest ?? '')),
            $this->formatDate((string) ($row->newest ?? '')),
            (string) ($row->types ?? '-')
        ));
    }

    private function formatDate(string $dt): string
    {
        if ($dt === '') {
            return '-';
        }
        // ISO datetime -> just keep the date prefix for readability.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $dt, $m)) {
            return $m[1];
        }
        return $dt;
    }
}
