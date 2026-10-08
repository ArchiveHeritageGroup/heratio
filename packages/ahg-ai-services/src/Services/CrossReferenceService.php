<?php

/**
 * CrossReferenceService - cross-reference the items of a collection from
 * their text (heratio#1524, phase 1).
 *
 *   sameStory()       near-duplicate items: the same telegram printed by
 *                     different papers, pasted in more than one place. Word
 *                     6-gram fingerprints; two items are linked when one
 *                     contains 30% or more of the other's. Phrases shared by
 *                     more than 20 items are stock phrases and ignored. No AI.
 *   duplicateLeaves() digitisation QA: pages (parents) that share 3 or more
 *                     near-identical items, the sign of a leaf photographed
 *                     twice.
 *   entities()        people, places, units and newspapers per item, through
 *                     the gateway (qwen3:14b, JSON), kept only when the name is
 *                     printed word for word in the item's own text, and
 *                     written as pending NER entities for the authority review
 *                     queue.
 *
 * Measured on the Brenthurst Library cuttings (October 2026): 4,993 items,
 * 126 same-story links (94 across volumes) and four leaves photographed twice.
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

declare(strict_types=1);

namespace AhgAiServices\Services;

use AhgAiServices\Support\AiServicesSettings;
use AhgAiServices\Support\NerExtractionLedger;
use AhgCore\Services\HierarchyQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrossReferenceService
{
    /** relation.type_id for "related material descriptions". */
    public const RELATED_TYPE = 176;

    /** relation_i18n.description prefix that marks links this service owns. */
    public const LINK_NOTE = 'Same story: ';

    private const GRAM = 6;

    /** @var callable|null fn(string $text): ?array, for tests */
    private $extractor;

    public function __construct(?callable $extractor = null)
    {
        $this->extractor = $extractor;
    }

    /**
     * Item id => text for a record and everything beneath it: OCR text where
     * there is any, else the scope and content.
     *
     * @return array<int, string>
     */
    public function items(int $rootId): array
    {
        $ids = app(HierarchyQueryService::class)->descendantIds('information_object', $rootId, true);
        $texts = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $ocr = DB::table('iiif_ocr_text')->whereIn('object_id', $chunk)->orderBy('id')
                ->get(['object_id', 'full_text'])->groupBy('object_id')
                ->map(fn ($rows) => trim($rows->pluck('full_text')->implode("\n")));
            $scope = DB::table('information_object_i18n')->whereIn('id', $chunk)->where('culture', 'en')->pluck('scope_and_content', 'id');
            foreach ($chunk as $id) {
                $t = (string) (($ocr[$id] ?? '') !== '' ? $ocr[$id] : ($scope[$id] ?? ''));
                if (trim($t) !== '') {
                    $texts[(int) $id] = $t;
                }
            }
        }

        return $texts;
    }

    /**
     * Pairs of items that tell the same story.
     *
     * @param  array<int, string>  $texts
     * @return list<array{a: int, b: int, overlap: float, a_share: float, b_share: float}>
     */
    public function sameStory(array $texts, float $minOverlap = 0.3, int $stockLimit = 20): array
    {
        $grams = array_map(fn ($t) => $this->grams($t), $texts);
        $df = [];
        foreach ($grams as $set) {
            foreach ($set as $g => $_) {
                $df[$g] = ($df[$g] ?? 0) + 1;
            }
        }
        $index = [];
        foreach ($grams as $id => $set) {
            foreach ($set as $g => $_) {
                if ($df[$g] >= 2 && $df[$g] <= $stockLimit) {
                    $index[$g][] = $id;
                }
            }
        }
        $shared = [];
        foreach ($index as $ids) {
            $n = count($ids);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    [$a, $b] = $ids[$i] < $ids[$j] ? [$ids[$i], $ids[$j]] : [$ids[$j], $ids[$i]];
                    $shared["{$a}:{$b}"] = ($shared["{$a}:{$b}"] ?? 0) + 1;
                }
            }
        }
        $pairs = [];
        foreach ($shared as $key => $n) {
            [$a, $b] = array_map('intval', explode(':', $key));
            $sa = $n / max(1, count($grams[$a]));
            $sb = $n / max(1, count($grams[$b]));
            if (max($sa, $sb) >= $minOverlap) {
                $pairs[] = ['a' => $a, 'b' => $b, 'overlap' => round(max($sa, $sb), 3), 'a_share' => round($sa, 3), 'b_share' => round($sb, 3)];
            }
        }
        usort($pairs, fn ($x, $y) => $y['overlap'] <=> $x['overlap'] ?: $x['a'] <=> $y['a']);

        return $pairs;
    }

    /**
     * Pages (parents) sharing $minShared or more near-identical items.
     *
     * @param  list<array{a: int, b: int, a_share: float, b_share: float}>  $pairs
     * @return list<array{page_a: int, page_b: int, shared_items: int}>
     */
    public function duplicateLeaves(array $pairs, int $minShared = 3, float $identical = 0.9): array
    {
        $ids = array_unique(array_merge(array_column($pairs, 'a'), array_column($pairs, 'b')));
        $parent = $ids ? DB::table('information_object')->whereIn('id', $ids)->pluck('parent_id', 'id')->map('intval')->all() : [];
        $count = [];
        foreach ($pairs as $p) {
            if (min($p['a_share'], $p['b_share']) < $identical) {
                continue;
            }
            $pa = $parent[$p['a']] ?? 0;
            $pb = $parent[$p['b']] ?? 0;
            if ($pa && $pb && $pa !== $pb) {
                $key = min($pa, $pb).':'.max($pa, $pb);
                $count[$key] = ($count[$key] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($count as $key => $n) {
            if ($n >= $minShared) {
                [$a, $b] = array_map('intval', explode(':', $key));
                $out[] = ['page_a' => $a, 'page_b' => $b, 'shared_items' => $n];
            }
        }

        return $out;
    }

    /**
     * Replace this service's same-story links among $ids with $pairs. Links a
     * person made, or other relation types, are left alone.
     *
     * @param  list<int>  $ids
     */
    public function storeLinks(array $ids, array $pairs): int
    {
        return DB::transaction(function () use ($ids, $pairs) {
            $old = DB::table('relation as r')->join('relation_i18n as ri', 'ri.id', '=', 'r.id')
                ->where('r.type_id', self::RELATED_TYPE)->whereIn('r.subject_id', $ids)->whereIn('r.object_id', $ids)
                ->where('ri.description', 'like', self::LINK_NOTE.'%')->pluck('r.id')->all();
            foreach (array_chunk($old, 500) as $chunk) {
                DB::table('relation_i18n')->whereIn('id', $chunk)->delete();
                DB::table('relation')->whereIn('id', $chunk)->delete();
                DB::table('object')->whereIn('id', $chunk)->delete();
            }
            $n = 0;
            foreach ($pairs as $p) {
                $exists = DB::table('relation')->where('type_id', self::RELATED_TYPE)
                    ->where(fn ($q) => $q->where(fn ($w) => $w->where('subject_id', $p['a'])->where('object_id', $p['b']))
                        ->orWhere(fn ($w) => $w->where('subject_id', $p['b'])->where('object_id', $p['a'])))->exists();
                if ($exists) {
                    continue;
                }
                $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('relation')->insert(['id' => $id, 'subject_id' => $p['a'], 'object_id' => $p['b'], 'type_id' => self::RELATED_TYPE, 'source_culture' => 'en']);
                DB::table('relation_i18n')->insert(['id' => $id, 'culture' => 'en',
                    'description' => self::LINK_NOTE.round($p['overlap'] * 100).'% of the wording in common (found by text comparison)']);
                $n++;
            }

            return $n;
        });
    }

    /**
     * Grounded entities for one item: names the model found that are printed
     * word for word in the text. Written as pending NER entities.
     *
     * @return list<array{type: string, value: string}>
     */
    public function entities(int $objectId, string $text, bool $write = true): array
    {
        $found = $this->extract($text) ?? [];
        $types = ['people' => 'PERSON', 'places' => 'GPE', 'units' => 'ORG', 'newspapers' => 'ORG'];
        $kept = [];
        foreach ($types as $key => $type) {
            foreach ((array) ($found[$key] ?? []) as $name) {
                $name = trim((string) $name);
                // Grounded: the printed form, never the model's "canonical" form.
                if (mb_strlen($name) >= 2 && mb_stripos($text, $name) !== false) {
                    $kept[$type.'|'.$name] = ['type' => $type, 'value' => $name];
                }
            }
        }
        $kept = array_values($kept);

        if ($write && $kept) {
            $extraction = NerExtractionLedger::open($objectId, 'llm_grounded');
            $written = 0;
            foreach ($kept as $e) {
                $dup = DB::table('ahg_ner_entity')->where('object_id', $objectId)
                    ->where('entity_type', $e['type'])->where('entity_value', $e['value'])->exists();
                if (! $dup) {
                    DB::table('ahg_ner_entity')->insert([
                        'extraction_id' => $extraction, 'object_id' => $objectId, 'entity_type' => $e['type'],
                        'entity_value' => $e['value'], 'confidence' => null, 'status' => 'pending', 'created_at' => now(),
                    ]);
                    $written++;
                }
            }
            NerExtractionLedger::complete($extraction, $written);
        }

        return $kept;
    }

    /** @return array<string, true> 6-gram set of an item's words */
    private function grams(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);
        $w = $m[0];
        $set = [];
        for ($i = 0; $i + self::GRAM <= count($w); $i++) {
            $set[implode(' ', array_slice($w, $i, self::GRAM))] = true;
        }

        return $set;
    }

    private function extract(string $text): ?array
    {
        if ($this->extractor !== null) {
            return ($this->extractor)($text);
        }
        $prompt = "List the people, places, military units and newspapers named in this text. Copy each name exactly as it is printed; do not expand, correct or combine names. Reply as JSON: {\"people\":[],\"places\":[],\"units\":[],\"newspapers\":[]}.\n\nText:\n".mb_substr($text, 0, 12000);
        try {
            $resp = Http::withToken((string) AiServicesSettings::gatewayKey())->timeout(180)
                ->post('https://ai.theahg.co.za/ai/v1/ollama/api/chat', [
                    'model' => (string) (DB::table('ahg_ai_settings')->where('feature', 'ner')->where('setting_key', 'cross_reference_model')->value('setting_value') ?: 'qwen3:14b'),
                    'stream' => false, 'format' => 'json', 'think' => false, 'options' => ['temperature' => 0],
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
            if ($resp->successful()) {
                $data = json_decode((string) $resp->json('message.content', ''), true);

                return is_array($data) ? $data : null;
            }
            Log::warning('[ahg-ai] cross-reference entity call failed', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] cross-reference entity call threw: '.$e->getMessage());
        }

        return null;
    }
}
