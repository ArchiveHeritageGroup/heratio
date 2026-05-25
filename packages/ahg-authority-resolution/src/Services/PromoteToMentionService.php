<?php

/**
 * PromoteToMentionService - Service for Heratio
 *
 * Orchestrator. Given a ner_entity_id, fetches the source text from the
 * owning information_object, asks ContextDerivationService for the neighbourhood
 * packet, then INSERTs ahg_mention + ahg_mention_context in a single transaction.
 *
 * Idempotent: re-promoting the same ner_entity_id is a no-op (UNIQUE on
 * ahg_mention.ner_entity_id makes this safe).
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

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromoteToMentionService
{
    private const SOURCE_TEXT_FIELDS = [
        'title',
        'scope_and_content',
        'extent_and_medium',
        'arrangement',
        'archival_history',
        'acquisition',
        'physical_characteristics',
    ];

    public function __construct(
        private ContextDerivationService $contextDeriver,
    ) {}

    /**
     * Promote a single ner_entity row to ahg_mention + ahg_mention_context.
     *
     * Returns the ahg_mention.id on success (or the existing one if already
     * promoted), or null if the ner_entity row is missing.
     *
     * @param  string|null  $sourceText  Exact text NER was run against (full match rate). If
     *                                   null, falls back to IO i18n concatenation (lossy
     *                                   when NER ran against digital-object content).
     * @param  array{start:int,end:int}|null  $knownOffset  Exact character offsets from the
     *                                                      upstream NER API (entities_v2). When
     *                                                      given, context derivation skips the
     *                                                      lossy stripos scan. Null = legacy path.
     * @param  float|null  $realConfidence  Per-entity confidence score from the upstream API
     *                                      (entities_v2 score). Written to
     *                                      ahg_mention_context.real_confidence. Null when the
     *                                      API exposes no per-entity score (spaCy default).
     */
    public function promote(
        int $nerEntityId,
        ?string $sourceText = null,
        ?array $knownOffset = null,
        ?float $realConfidence = null
    ): ?int {
        $entity = DB::table('ahg_ner_entity')->where('id', $nerEntityId)->first();
        if (! $entity) {
            return null;
        }

        $existing = DB::table('ahg_mention')->where('ner_entity_id', $nerEntityId)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $sourceText = $sourceText ?? $this->fetchSourceText((int) $entity->object_id);
        $others = $this->fetchOtherEntities((int) $entity->object_id, $nerEntityId);
        $roleTokens = $this->loadRoleLanguageTokens();

        $context = $this->contextDeriver->derive(
            $sourceText,
            (string) $entity->entity_value,
            (string) $entity->entity_type,
            $others,
            $roleTokens,
            $knownOffset
        );

        return DB::transaction(function () use ($entity, $context, $realConfidence) {
            $now = now();
            $mentionId = DB::table('ahg_mention')->insertGetId([
                'ner_entity_id' => $entity->id,
                'object_id' => $entity->object_id,
                'entity_type' => $entity->entity_type,
                'state' => 'pending',
                'promoted_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('ahg_mention_context')->insert([
                'mention_id' => $mentionId,
                'character_offset_start' => $context['character_offset_start'],
                'character_offset_end' => $context['character_offset_end'],
                'paragraph_offset_start' => $context['paragraph_offset_start'],
                'paragraph_offset_end' => $context['paragraph_offset_end'],
                'surrounding_text_before' => $context['surrounding_text_before'],
                'surrounding_text_after' => $context['surrounding_text_after'],
                'ner_model_version' => null,
                'real_confidence' => $realConfidence,
                'co_occurring_entities' => json_encode($context['co_occurring_entities'], JSON_UNESCAPED_UNICODE),
                'nearby_dates' => json_encode($context['nearby_dates'], JSON_UNESCAPED_UNICODE),
                'nearby_places' => json_encode($context['nearby_places'], JSON_UNESCAPED_UNICODE),
                'role_language_tokens' => json_encode($context['role_language_tokens'], JSON_UNESCAPED_UNICODE),
                'computed_at' => $now,
            ]);

            return $mentionId;
        });
    }

    /**
     * Promote every ner_entity row for a given object_id. Returns the count
     * of newly-promoted rows (excludes ones that were already promoted).
     */
    public function promoteAllForObject(int $objectId): int
    {
        $rows = DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->whereIn('entity_type', ['PERSON', 'ORG', 'GPE', 'PLACE', 'LOC'])
            ->pluck('id');

        $newCount = 0;
        foreach ($rows as $id) {
            $existing = DB::table('ahg_mention')->where('ner_entity_id', $id)->exists();
            if ($existing) {
                continue;
            }
            try {
                $this->promote((int) $id);
                $newCount++;
            } catch (\Throwable $e) {
                Log::warning('PromoteToMentionService::promoteAllForObject failed for ner_entity_id='.$id.': '.$e->getMessage());
            }
        }

        return $newCount;
    }

    /**
     * Concatenates the IO's text fields across all i18n rows. Same source-text
     * shape the existing NerService::extract() uses upstream.
     */
    /**
     * Exposed as public so Task 9 NerFeedbackService can reuse the same
     * IO-i18n concatenation contract without duplicating the field list.
     */
    public function fetchSourceText(int $objectId): string
    {
        $rows = DB::table('information_object_i18n')->where('id', $objectId)->get();
        $parts = [];
        foreach ($rows as $row) {
            foreach (self::SOURCE_TEXT_FIELDS as $field) {
                $val = $row->$field ?? null;
                if (is_string($val) && trim($val) !== '') {
                    $parts[] = $val;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<array{ner_entity_id:int,value:string,type:string}>
     */
    private function fetchOtherEntities(int $objectId, int $excludeNerEntityId): array
    {
        return DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->where('id', '!=', $excludeNerEntityId)
            ->get(['id', 'entity_type', 'entity_value'])
            ->map(fn ($r) => [
                'ner_entity_id' => (int) $r->id,
                'type' => (string) $r->entity_type,
                'value' => (string) $r->entity_value,
            ])
            ->all();
    }

    /**
     * @return array<string,list<string>>
     */
    private function loadRoleLanguageTokens(): array
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'authority_resolution')
            ->where('setting_key', 'authority_resolution.role_language_tokens')
            ->first();

        if (! $row || empty($row->setting_value)) {
            return [];
        }

        $decoded = json_decode($row->setting_value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
