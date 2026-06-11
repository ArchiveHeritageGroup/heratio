<?php

/**
 * DisplacedHeritageScanCommand - run the potentially-displaced-heritage scan from
 * the CLI (first slice of the repatriation engine, north-star heratio#1207).
 *
 *   php artisan ahg:displaced-heritage-scan --limit=20
 *
 * Prints the conservative origin-vs-holding mismatch register produced by
 * DisplacedHeritageService::scan(): a per-origin-region summary plus a table of
 * flagged records (object, origin, current holding, reason). It always leads and
 * closes with the "review aid, not a claim" disclaimer. --limit=0 means no cap.
 * The command never throws on the scan path.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Console\Commands;

use AhgSemanticSearch\Services\DisplacedHeritageService;
use Illuminate\Console\Command;

class DisplacedHeritageScanCommand extends Command
{
    protected $signature = 'ahg:displaced-heritage-scan
        {--limit=0 : Maximum flagged records to list (0 = no cap)}';

    protected $description = 'Scan museum records for origin-vs-holding mismatches - a curatorial review register, not a repatriation claim (heratio#1207)';

    public function handle(DisplacedHeritageService $service): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 0) {
            $limit = 0;
        }

        $report = $service->scan(['limit' => $limit]);

        $this->newLine();
        $this->warn('REVIEW AID - NOT A REPATRIATION CLAIM OR LEGAL DETERMINATION');
        $this->line($this->wrap($report['disclaimer']));
        $this->newLine();

        $this->line(sprintf(
            '<info>Scanned:</info> %d museum records; <info>evaluated:</info> %d with a known origin AND a known holding region; <info>flagged:</info> %d mismatch(es).',
            (int) $report['scanned'],
            (int) $report['evaluated'],
            (int) $report['flagged_count']
        ));
        if (! empty($report['truncated'])) {
            $this->comment(sprintf('(showing the first %d - re-run with a higher --limit to see all)', (int) $report['limit']));
        }
        $this->newLine();

        if (empty($report['records'])) {
            $this->info('No origin-vs-holding mismatches flagged. (Records where origin or holding could not be confidently placed are left unflagged on purpose.)');

            return self::SUCCESS;
        }

        // Summary by origin region.
        $this->line('<comment>Summary by origin region:</comment>');
        foreach ($report['by_origin'] as $grp) {
            $this->line(sprintf('  - %s: %d', $grp['region'], (int) $grp['count']));
        }
        $this->newLine();

        // Detail table.
        $rows = [];
        foreach ($report['records'] as $rec) {
            $title = $rec['title'] ?? ('#'.$rec['id']);
            $rows[] = [
                $rec['id'],
                $this->truncate((string) $title, 38),
                $rec['origin_region'].' ('.$this->truncate($rec['origin']['value'], 24).')',
                $rec['holding_region'].' ('.$this->truncate($rec['holding']['value'], 24).')',
            ];
        }

        $this->table(
            ['ID', 'Title', 'Recorded origin', 'Current holding'],
            $rows
        );

        $this->newLine();
        $this->warn('Each row is a lead for curatorial review only. It is NOT a finding of wrongful removal and NOT legal advice.');

        return self::SUCCESS;
    }

    /**
     * Wrap a long disclaimer to ~100 cols for terminal readability.
     */
    protected function wrap(string $text): string
    {
        return wordwrap(trim((string) preg_replace('/\s+/', ' ', $text)), 100, "\n", true);
    }

    /**
     * Single-line truncate for table cells.
     */
    protected function truncate(string $value, int $max): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
