<?php

/**
 * ahg:cross-reference - same-story links, duplicate-leaf QA, grounded
 * entities and the gazetteer check of place names for one collection
 * (heratio#1524).
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

namespace AhgAiServices\Commands;

use AhgAiServices\Services\CrossReferenceService;
use AhgAiServices\Services\GazetteerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrossReferenceCommand extends Command
{
    protected $signature = 'ahg:cross-reference
        {slug : The fonds or collection (its slug); everything beneath it is compared}
        {--min-overlap=0.3 : Link two items when one contains this share of the other\'s 6-word phrases}
        {--stock=20 : Ignore phrases shared by more items than this}
        {--entities : Also extract grounded entities through the gateway (one model call per item)}
        {--report= : Write the duplicate-leaf QA report to this CSV file}
        {--places= : Write the gazetteer review list of the collection\'s place names to this CSV file (see ahg:gazetteer-load)}
        {--dry-run : Report what would be linked; write nothing}';

    protected $description = 'Cross-reference a collection from its text: same-story links, leaves photographed twice, grounded entities, place-name check';

    public function handle(CrossReferenceService $svc, GazetteerService $gazetteer): int
    {
        $rootId = (int) DB::table('slug')->join('information_object', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $this->argument('slug'))->value('slug.object_id');
        if (! $rootId) {
            $this->error('No archival description has that slug.');

            return self::FAILURE;
        }

        $texts = $svc->items($rootId);
        $this->info(count($texts).' item(s) with text.');
        $pairs = $svc->sameStory($texts, (float) $this->option('min-overlap'), (int) $this->option('stock'));
        $this->line(count($pairs).' same-story pair(s).');

        $leaves = $svc->duplicateLeaves($pairs);
        $this->line(count($leaves).' page pair(s) sharing 3 or more identical items (possible leaf photographed twice).');
        foreach (array_slice($leaves, 0, 20) as $l) {
            $this->line(sprintf('  pages %d and %d share %d item(s)', $l['page_a'], $l['page_b'], $l['shared_items']));
        }
        if ($path = $this->option('report')) {
            $fp = fopen($path, 'w');
            fputcsv($fp, ['page_a', 'page_a_slug', 'page_b', 'page_b_slug', 'shared_items']);
            $slugs = DB::table('slug')->whereIn('object_id', array_merge(array_column($leaves, 'page_a'), array_column($leaves, 'page_b')))->pluck('slug', 'object_id');
            foreach ($leaves as $l) {
                fputcsv($fp, [$l['page_a'], $slugs[$l['page_a']] ?? '', $l['page_b'], $slugs[$l['page_b']] ?? '', $l['shared_items']]);
            }
            fclose($fp);
            $this->line("QA report: {$path}");
        }

        if (! $this->option('dry-run')) {
            // One cutting on a leaf photographed twice is not a story printed twice.
            $links = $svc->withoutLeafRepeats($pairs, $leaves);
            $this->info($svc->storeLinks(array_keys($texts), $links).' related-description link(s) written.');
        }

        if ($this->option('entities') && ! $this->option('dry-run')) {
            $bar = $this->output->createProgressBar(count($texts));
            $kept = $queued = 0;
            foreach ($texts as $id => $text) {
                $names = $svc->entities($id, $text);
                $kept += count($names);
                $queued += $svc->promoteToAuthorityQueue($id, $text, $names);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info("{$kept} grounded entit(ies) kept for review (NER review queue); {$queued} new authority candidate(s) queued.");
        }

        if ($path = $this->option('places')) {
            $places = array_values(array_filter($svc->groupedEntities(array_keys($texts)), fn ($e) => $e['type'] === 'place'));
            $rows = $gazetteer->check($places);
            GazetteerService::writeCsv($path, $rows);
            $count = array_count_values(array_map(fn ($r) => strtok($r['status'], ' '), $rows));
            $this->line(sprintf('%d place(s): %d found, %d possible misspelling(s), %d not in the gazetteer. Review list: %s',
                count($rows), $count['found'] ?? 0, $count['possible'] ?? 0, $count['not'] ?? 0, $path));
        }

        return self::SUCCESS;
    }
}
