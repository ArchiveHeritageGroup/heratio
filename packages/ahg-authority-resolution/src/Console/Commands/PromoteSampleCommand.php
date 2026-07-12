<?php

/**
 * PromoteSampleCommand - Console command for Heratio
 *
 * Demonstrate authority-resolution mention extraction with neighbourhood
 * context against a sample information object. Used for the Task 2
 * pause-gate demo. Idempotent (safe to re-run).
 *
 * Usage:
 *   php artisan auth-res:promote-sample {object_id} [--show]
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

use AhgAuthorityResolution\Services\PromoteToMentionService;
use AhgAuthorityResolution\Support\MentionVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteSampleCommand extends Command
{
    protected $signature = 'auth-res:promote-sample
                            {object_id : Information object ID to promote mentions for}
                            {--show : Print full context packets for the promoted mentions}
                            {--limit=5 : Max promoted mentions to display when --show is set}';

    protected $description = 'Promote PERSON/ORG/GPE/PLACE entities for an information object into the authority-resolution mention workflow with neighbourhood context.';

    public function handle(PromoteToMentionService $promoter): int
    {
        $objectId = (int) $this->argument('object_id');

        $entityCount = DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->whereIn('entity_type', MentionVocabulary::ENTITY_TYPES)
            ->count();

        if ($entityCount === 0) {
            $this->error("No PERSON/ORG/GPE entities found for object_id={$objectId} in ahg_ner_entity.");

            return self::FAILURE;
        }

        $this->info("Object {$objectId}: {$entityCount} resolvable NER entities found. Promoting...");

        $newCount = $promoter->promoteAllForObject($objectId);

        $this->info("Promoted: {$newCount} new mentions. (Idempotent: existing mentions skipped.)");

        $totalMentions = DB::table('ahg_mention')
            ->where('object_id', $objectId)
            ->count();
        $this->line("Total ahg_mention rows for object {$objectId}: {$totalMentions}");

        if ($this->option('show')) {
            $this->printMentionDetail($objectId, (int) $this->option('limit'));
        }

        return self::SUCCESS;
    }

    private function printMentionDetail(int $objectId, int $limit): void
    {
        $rows = DB::table('ahg_mention as m')
            ->leftJoin('ahg_mention_context as c', 'c.mention_id', '=', 'm.id')
            ->leftJoin('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.object_id', $objectId)
            ->orderBy('m.id', 'desc')
            ->limit($limit)
            ->get([
                'm.id as mention_id',
                'n.entity_value',
                'm.entity_type',
                'm.state',
                'c.character_offset_start',
                'c.character_offset_end',
                'c.surrounding_text_before',
                'c.surrounding_text_after',
                'c.co_occurring_entities',
                'c.nearby_dates',
                'c.nearby_places',
                'c.role_language_tokens',
            ]);

        foreach ($rows as $row) {
            $this->newLine();
            $this->line("--- mention #{$row->mention_id} ---");
            $this->line("  entity        : [{$row->entity_type}] {$row->entity_value}");
            $this->line("  state         : {$row->state}");
            $this->line("  offsets       : {$row->character_offset_start}-{$row->character_offset_end}");
            $before = trim((string) $row->surrounding_text_before);
            $after = trim((string) $row->surrounding_text_after);
            $this->line('  before (~150) : '.$this->truncate($before, 120));
            $this->line('  after  (~150) : '.$this->truncate($after, 120));
            $this->printJsonList('co_occurring ', $row->co_occurring_entities);
            $this->printJsonList('nearby_dates ', $row->nearby_dates);
            $this->printJsonList('nearby_places', $row->nearby_places);
            $this->printJsonList('role_language', $row->role_language_tokens);
        }
    }

    private function printJsonList(string $label, ?string $json): void
    {
        $items = json_decode((string) $json, true);
        if (! is_array($items) || empty($items)) {
            $this->line("  {$label} : (empty)");

            return;
        }
        $count = count($items);
        $preview = array_slice($items, 0, 5);
        $summary = array_map(function ($item) {
            if (is_array($item)) {
                if (isset($item['token'])) {
                    return "[{$item['kind']}] '{$item['token']}'";
                }
                $v = $item['value'] ?? '';
                $t = $item['type'] ?? '';
                $d = $item['distance_chars'] ?? '?';

                return "[{$t}] '{$v}' (d={$d})";
            }

            return (string) $item;
        }, $preview);
        $more = $count > 5 ? '  ... +'.($count - 5).' more' : '';
        $this->line("  {$label} : {$count} items");
        foreach ($summary as $s) {
            $this->line("                  - {$s}");
        }
        if ($more) {
            $this->line($more);
        }
    }

    private function truncate(string $s, int $max): string
    {
        $s = str_replace(["\n", "\r"], ' ', $s);
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max - 3).'...';
    }
}
