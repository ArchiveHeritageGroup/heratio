<?php

/**
 * ahg:cross-reference - same-story links, duplicate-leaf QA and grounded
 * entities for one collection (heratio#1524, phase 1).
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
        {--dry-run : Report what would be linked; write nothing}';

    protected $description = 'Cross-reference a collection from its text: same-story links, leaves photographed twice, grounded entities';

    public function handle(CrossReferenceService $svc): int
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

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->info($svc->storeLinks(array_keys($texts), $pairs).' related-description link(s) written.');

        if ($this->option('entities')) {
            $bar = $this->output->createProgressBar(count($texts));
            $kept = 0;
            foreach ($texts as $id => $text) {
                $kept += count($svc->entities($id, $text));
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info("{$kept} grounded entit(ies) kept for review (NER review queue).");
        }

        return self::SUCCESS;
    }
}
