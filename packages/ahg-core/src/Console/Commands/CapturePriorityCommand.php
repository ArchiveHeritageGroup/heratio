<?php

/**
 * CapturePriorityCommand - Console command for Heratio
 *
 * heratio#1205 north-star, first slice. CLI view of the capture / at-risk register:
 * the records most in need of digitisation or most at risk of loss, ranked by the
 * same transparent signals as the admin report, each with a plain-language reason
 * list. Read-only - it only reports.
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
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\CapturePriorityService;
use Illuminate\Console\Command;

class CapturePriorityCommand extends Command
{
    protected $signature = 'ahg:capture-priority '
        .'{--limit=0 : Max records to list (0 = all). Ranking always uses the full set.}';

    protected $description = 'List the records most in need of digitisation / most at risk of loss, with reasons';

    public function handle(CapturePriorityService $service): int
    {
        $limit = max(0, (int) $this->option('limit'));

        $report = $service->register(['limit' => $limit]);
        $s = $report['summary'];

        $this->info('Capture-priority register  (generated '.$report['generated_at'].')');
        $this->line(sprintf(
            'Records scanned: %d   At-risk (scored): %d   No master surrogate: %d   Poor condition: %d   Endangerment flags: %d',
            $s['total'], $s['scored'], $s['no_master'], $s['poor_condition'], $s['endangered']
        ));

        if (! $report['notes']['condition_reports']) {
            $this->warn('Note: condition_report table not present - condition signals skipped.');
        }
        if (! $report['notes']['museum_metadata']) {
            $this->warn('Note: museum_metadata table not present - museum condition signals skipped.');
        }

        if (! empty($report['reason_counts'])) {
            $this->newLine();
            $this->line('<comment>Counts by reason:</comment>');
            foreach ($report['reason_counts'] as $reason => $count) {
                $this->line(sprintf('  %5d  %s', $count, $reason));
            }
        }

        if (empty($report['rows'])) {
            $this->newLine();
            $this->info('No at-risk records detected from the current catalogue signals.');

            return self::SUCCESS;
        }

        $this->newLine();
        $rows = [];
        foreach ($report['rows'] as $i => $r) {
            $rows[] = [
                $i + 1,
                $r['score'],
                $r['id'],
                $this->truncate((string) $r['title'], 48),
                $this->truncate(implode('; ', $r['reasons']), 70),
            ];
        }
        $this->table(['#', 'Score', 'ID', 'Title', 'Reasons'], $rows);

        return self::SUCCESS;
    }

    private function truncate(string $s, int $len): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1).'…' : $s;
    }
}
