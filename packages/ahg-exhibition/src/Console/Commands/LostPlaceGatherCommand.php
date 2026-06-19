<?php

/**
 * LostPlaceGatherCommand - #1323 "Lost Places" POC, increment 1.
 *
 * `php artisan ahg:lost-place-gather "Notre-Dame"` resolves a place, lists every
 * archival record linked to it with its media counts, and prints a coverage
 * metric judging whether there is enough imagery to attempt a 3D reconstruction.
 * `--json` emits the machine-readable gather manifest (the hand-off to the
 * 3D-rebuild step, ahg-3d-model).
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

namespace AhgExhibition\Console\Commands;

use AhgExhibition\Services\LostPlaceGatherService;
use Illuminate\Console\Command;

class LostPlaceGatherCommand extends Command
{
    protected $signature = 'ahg:lost-place-gather
                            {place : place name (partial ok) or place-taxonomy term id}
                            {--limit=0 : cap the number of records gathered (0 = all)}
                            {--discover : also surface unlinked look-alike photos via CLIP (#1272)}
                            {--json : emit the gather manifest as JSON}';

    protected $description = 'Lost Places POC (#1323): gather the archival evidence linked to a place + a reconstruction-coverage metric.';

    public function handle(LostPlaceGatherService $service): int
    {
        $query = (string) $this->argument('place');
        $result = $service->gather($query, (int) $this->option('limit'));

        if ($this->option('discover') && $result['place']) {
            $result['discovery'] = $service->discoverCandidates((int) $result['place']['term_id']);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $result['place']) {
            $this->error("No place matched \"{$query}\" in the places taxonomy.");

            return self::FAILURE;
        }

        $place = $result['place'];
        $cov = $result['coverage'];

        $this->info("Place: {$place['name']}  (term #{$place['term_id']})");
        $this->line('  In RiC graph (rico:Place): '.($result['in_ric_graph'] ? 'yes' : 'no'));
        $this->newLine();

        if ($result['records']) {
            $this->table(
                ['IO', 'Identifier', 'Title', 'Images', 'Docs'],
                array_map(static fn ($r) => [
                    $r['id'],
                    $r['identifier'] ?? '-',
                    mb_strimwidth((string) $r['title'], 0, 50, '...'),
                    $r['image_count'],
                    $r['document_count'],
                ], $result['records'])
            );
        } else {
            $this->warn('  No records are linked to this place.');
        }

        $this->newLine();
        $this->info('Coverage summary');
        $this->line("  Records linked .......... {$cov['records_total']}");
        $this->line("  With media .............. {$cov['records_with_media']} ({$cov['coverage_pct']}%)");
        $this->line("  Images / Documents ...... {$cov['image_total']} / {$cov['document_total']}");
        $this->line("  Reconstruction level .... ".strtoupper($cov['reconstruction_level']));
        $this->line('  '.$cov['reconstruction_note']);

        if (isset($result['discovery'])) {
            $d = $result['discovery'];
            $this->newLine();
            $this->info('Unlinked candidate photos (CLIP visual discovery, #1272)');
            if (! $d['available']) {
                $this->warn('  Unavailable: '.($d['note'] ?? 'discovery stack not present.'));
            } elseif (! $d['candidates']) {
                $this->line('  '.($d['note'] ?? 'No candidates found.'));
            } else {
                $this->line("  Seeded from {$d['seeds']} linked image(s); ".count($d['candidates']).' candidate(s):');
                foreach (array_slice($d['candidates'], 0, 10) as $c) {
                    $this->line(sprintf('   IO %-8d  score %.3f  %s', $c['information_object_id'], $c['score'], $c['title'] ?? ''));
                }
            }
        }

        return self::SUCCESS;
    }
}
