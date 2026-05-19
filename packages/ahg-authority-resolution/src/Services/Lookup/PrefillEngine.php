<?php

/**
 * PrefillEngine - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Glue between the mention
 * + its context packet, the seven external authority adapters, and the
 * "Create new authority" form.
 *
 * The engine is the single entry point the controller calls. It does not
 * make HTTP calls itself - the adapters do (they own the enabled/cache/
 * rate-limit logic). The engine's job is:
 *
 *   1. Load mention + ahg_mention_context + ahg_ner_entity.entity_value
 *      (so the form has a fallback name even when every external source
 *      is disabled).
 *   2. Iterate the registered adapters that `supports($entityType)`.
 *   3. Collect each adapter's top results into `lookup_results[<source>]`.
 *   4. Merge a single best-guess set of pre-fill fields using the
 *      precedence list (lookup.precedence). Tag each field with its
 *      source so the form badge can show provenance.
 *
 * Output shape - the controller passes this straight to the view AND uses
 * `_provenance` when calling FieldProvenanceWriter on submit.
 *
 *   [
 *     'mention' => {...row...},
 *     'context' => {...packet...},
 *     'lookup_results' => [
 *       'viaf' => [...candidates...],
 *       'wikidata' => [...candidates...],
 *     ],
 *     'merged_fields' => [
 *       'authorized_form_of_name' => 'Nelson Mandela',
 *       'dates_of_existence'      => '1918-2013',
 *       'history'                 => '...',
 *       '_provenance' => [
 *         'authorized_form_of_name' => [...],
 *         ...
 *       ],
 *     ],
 *   ]
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup;

use Illuminate\Support\Facades\DB;

class PrefillEngine
{
    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    private const DEFAULT_PRECEDENCE = ['viaf', 'wikidata', 'geonames', 'tgn', 'gnd', 'isni', 'sagnc'];

    /** @var list<LookupAdapterInterface> */
    private array $adapters;

    /**
     * @param iterable<LookupAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->adapters = [];
        foreach ($adapters as $adapter) {
            $this->adapters[] = $adapter;
        }
    }

    public function prefill(int $mentionId, ?string $forceSource = null, int $perSourceLimit = 5): array
    {
        $mention = $this->loadMention($mentionId);
        if (!$mention) {
            return [
                'mention' => null,
                'context' => null,
                'lookup_results' => [],
                'merged_fields' => ['_provenance' => []],
            ];
        }

        $context = DB::table('ahg_mention_context')->where('mention_id', $mentionId)->first();

        $entityType = (string) $mention->entity_type;
        $query = trim((string) $mention->entity_value);

        $lookupResults = [];
        if ($query !== '') {
            foreach ($this->adapters as $adapter) {
                if ($forceSource !== null && $adapter->source() !== $forceSource) {
                    continue;
                }
                if (!$adapter->supports($entityType)) {
                    continue;
                }
                $candidates = $adapter->search($query, $entityType, $perSourceLimit);
                if (!empty($candidates)) {
                    $lookupResults[$adapter->source()] = $candidates;
                }
            }
        }

        $merged = $this->mergeFields($lookupResults, $entityType, $mention, $context);

        return [
            'mention' => $mention,
            'context' => $context,
            'lookup_results' => $lookupResults,
            'merged_fields' => $merged,
        ];
    }

    /**
     * Best-effort merge of every source's top hit into a single draft
     * authority record. Highest-precedence source wins for any given
     * field. Each merged field carries provenance for the writer.
     *
     * @param array<string, list<array<string,mixed>>> $lookupResults
     */
    private function mergeFields(array $lookupResults, string $entityType, object $mention, ?object $context): array
    {
        $precedence = $this->loadPrecedence();
        $isPlace = in_array($entityType, self::PLACE_TYPES, true);

        $merged = [];
        $provenance = [];

        // Fallback name: always use the mention value if no source supplies one.
        $nameKey = $isPlace ? 'name' : 'authorized_form_of_name';
        $merged[$nameKey] = (string) ($mention->entity_value ?? '');
        $provenance[$nameKey] = [
            'source' => 'mention',
            'uri' => null,
            'licence' => null,
            'licence_url' => null,
            'retrieved_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Pre-seed entity type / descriptive standard.
        if ($isPlace) {
            $merged['descriptive_standard'] = 'ISDF';
        } else {
            $merged['entity_type'] = $entityType;
            $merged['descriptive_standard'] = 'ISAAR-CPF';
        }

        // Walk precedence list, top hit per source, fold any fields that
        // aren't already set (or were set from a lower-precedence source).
        $appliedRank = []; // field => rank index (lower is better)
        foreach ($precedence as $idx => $source) {
            if (!isset($lookupResults[$source])) {
                continue;
            }
            $candidate = $lookupResults[$source][0] ?? null;
            if (!$candidate) {
                continue;
            }
            $fields = is_array($candidate['fields'] ?? null) ? $candidate['fields'] : [];

            // Promote a couple of top-level fields too if subclasses didn't.
            if (!empty($candidate['authorized_name']) && $isPlace === false && !isset($fields['authorized_form_of_name'])) {
                $fields['authorized_form_of_name'] = (string) $candidate['authorized_name'];
            }
            if (!empty($candidate['authorized_name']) && $isPlace && !isset($fields['name'])) {
                $fields['name'] = (string) $candidate['authorized_name'];
            }
            if (!empty($candidate['dates_of_existence']) && !isset($fields['dates_of_existence'])) {
                $fields['dates_of_existence'] = (string) $candidate['dates_of_existence'];
            }
            if (!empty($candidate['history_snippet']) && !isset($fields['history'])) {
                $fields['history'] = (string) $candidate['history_snippet'];
            }

            foreach ($fields as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                // Promote into merged unless something earlier (higher
                // precedence) already filled it.
                if (isset($appliedRank[$key]) && $appliedRank[$key] < $idx) {
                    continue;
                }
                $merged[$key] = $value;
                $appliedRank[$key] = $idx;
                $provenance[$key] = [
                    'source' => $source,
                    'uri' => $candidate['external_uri'] ?? null,
                    'licence' => $candidate['licence'] ?? null,
                    'licence_url' => $candidate['licence_url'] ?? null,
                    'retrieved_at' => $candidate['retrieved_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
                ];
            }
        }

        // Context-derived hints (no external source, just our own context packet).
        if (!$isPlace && $context && empty($merged['history']) && !empty($context->surrounding_text_before)) {
            $combined = trim((string) $context->surrounding_text_before . ' [' . ($mention->entity_value ?? '') . '] ' . (string) ($context->surrounding_text_after ?? ''));
            if ($combined !== '') {
                $merged['history'] = $combined;
                $provenance['history'] = [
                    'source' => 'mention_context',
                    'uri' => null,
                    'licence' => null,
                    'licence_url' => null,
                    'retrieved_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ];
            }
        }

        $merged['_provenance'] = $provenance;
        return $merged;
    }

    /**
     * @return list<string>
     */
    private function loadPrecedence(): array
    {
        try {
            $raw = DB::table('ahg_settings')->where('setting_key', 'lookup.precedence')->value('setting_value');
        } catch (\Throwable $e) {
            return self::DEFAULT_PRECEDENCE;
        }
        if (!is_string($raw) || $raw === '') {
            return self::DEFAULT_PRECEDENCE;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return self::DEFAULT_PRECEDENCE;
        }
        return array_values(array_filter(array_map('strval', $decoded)));
    }

    private function loadMention(int $mentionId): ?object
    {
        return DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->leftJoin('information_object as io', 'io.id', '=', 'm.object_id')
            ->where('m.id', $mentionId)
            ->first([
                'm.id',
                'm.entity_type',
                'm.state',
                'm.object_id',
                'm.ner_entity_id',
                'n.entity_value',
                'n.confidence',
                'io.identifier as io_identifier',
            ]);
    }
}
