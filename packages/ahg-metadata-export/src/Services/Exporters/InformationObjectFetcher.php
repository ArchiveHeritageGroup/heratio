<?php

/**
 * InformationObjectFetcher - shared record-fetch helpers for serializers.
 *
 * Centralises the queries the per-format serializers (Ead2002, Ead3, Mods,
 * Marcxml) need so each serializer doesn't reinvent the same joins.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\Concerns\FetchesRepositoryActor;
use AhgCore\Support\I18nFallback;

trait InformationObjectFetcher
{
    use FetchesRepositoryActor;

    protected function fetchIo(int $objectId, string $culture)
    {
        // The record in the requested language; if it has no text there (a visitor
        // or crawler browsing in fr/af/zu...), fall back to the record's own source
        // language, then the site default. Before this the export was empty and the
        // MARC binary encoder crashed with "invalid MARCXML input" (500).
        $row = $this->fetchIoIn($objectId, $culture);
        if ($row === null) {
            $source = (string) DB::table('information_object')->where('id', $objectId)->value('source_culture');
            foreach (array_unique(array_filter([$source, (string) config('app.fallback_locale', 'en'), 'en'])) as $c) {
                if ($c !== $culture && ($row = $this->fetchIoIn($objectId, $c)) !== null) {
                    break;
                }
            }
        }

        return $row;
    }

    private function fetchIoIn(int $objectId, string $culture)
    {
        return DB::table('information_object as io')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'io.id', $culture))
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'o.id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select([
                'io.id', 'io.identifier', 'io.level_of_description_id',
                'io.repository_id', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.source_culture',
                I18nFallback::col('i18n', 'title', 'title'), I18nFallback::col('i18n', 'extent_and_medium', 'extent_and_medium'), I18nFallback::col('i18n', 'archival_history', 'archival_history'),
                I18nFallback::col('i18n', 'acquisition', 'acquisition'), I18nFallback::col('i18n', 'scope_and_content', 'scope_and_content'), I18nFallback::col('i18n', 'appraisal', 'appraisal'),
                I18nFallback::col('i18n', 'accruals', 'accruals'), I18nFallback::col('i18n', 'arrangement', 'arrangement'), I18nFallback::col('i18n', 'access_conditions', 'access_conditions'),
                I18nFallback::col('i18n', 'reproduction_conditions', 'reproduction_conditions'), I18nFallback::col('i18n', 'physical_characteristics', 'physical_characteristics'),
                I18nFallback::col('i18n', 'finding_aids', 'finding_aids'), I18nFallback::col('i18n', 'location_of_originals', 'location_of_originals'),
                I18nFallback::col('i18n', 'location_of_copies', 'location_of_copies'), I18nFallback::col('i18n', 'related_units_of_description', 'related_units_of_description'),
                I18nFallback::col('i18n', 'rules', 'rules'), I18nFallback::col('i18n', 'sources', 'sources'), I18nFallback::col('i18n', 'revision_history', 'revision_history'),
                'o.created_at', 'o.updated_at', 's.slug',
            ])
            ->first();
    }


    protected function fetchEvents($io, string $culture)
    {
        return DB::table('event')
            ->tap(fn ($q) => I18nFallback::join($q, 'event_i18n', 'event.id', $culture))
            ->where('event.object_id', $io->id)
            ->select('event.id', 'event.type_id', 'event.actor_id',
                'event.start_date', 'event.end_date',
                I18nFallback::col('event_i18n', 'date', 'date_display'))
            ->get();
    }

    protected function fetchCreators($io, string $culture)
    {
        return DB::table('event')
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $culture, required: true))
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->whereNotNull('event.actor_id')
            ->select(I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'),
                'actor.entity_type_id', 'actor.id as actor_id')
            ->distinct()
            ->get();
    }

    protected function fetchAccessPoints($io, int $taxonomyId, string $culture)
    {
        return DB::table('object_term_relation')
            ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n', 'object_term_relation.term_id', $culture, required: true))
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->select(I18nFallback::col('term_i18n', 'name', 'name'))
            ->get();
    }

    protected function fetchNotes($io, string $culture)
    {
        return DB::table('note')
            ->tap(fn ($q) => I18nFallback::join($q, 'note_i18n', 'note.id', $culture, required: true))
            ->where('note.object_id', $io->id)
            ->select('note.type_id', I18nFallback::col('note_i18n', 'content', 'content'))
            ->get();
    }

    protected function fetchLevelName($io, string $culture): ?string
    {
        if (empty($io->level_of_description_id)) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $io->level_of_description_id)
            ->where('culture', $culture)
            ->value('name');
    }

    protected function fetchLanguages($io, string $culture)
    {
        return DB::table('object_term_relation')
            ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n', 'object_term_relation.term_id', $culture, required: true))
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->select(I18nFallback::col('term_i18n', 'name', 'name'))
            ->get();
    }

    protected function fetchDescendants($io, string $culture)
    {
        // heratio#1541: the subtree from the closure tables in sibling order;
        // nestRows() below renumbers lft/rgt so the serializers nest by them.
        $hierarchy = app(\AhgCore\Services\HierarchyQueryService::class);
        $query = DB::table('information_object')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n', 'information_object.id', $culture))
            ->tap(fn ($q) => $hierarchy->scopeDescendants($q, 'information_object', (int) $io->id, 'information_object.id', false))
            ->tap(fn ($q) => $hierarchy->applySiblingOrder($q, 'information_object', 'information_object.id', 'information_object.lft'))
            ->select([
                'information_object.id', 'information_object.parent_id', 'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.lft', 'information_object.rgt',
                I18nFallback::col('information_object_i18n', 'title', 'title'),
                I18nFallback::col('information_object_i18n', 'scope_and_content', 'scope_and_content'),
                I18nFallback::col('information_object_i18n', 'extent_and_medium', 'extent_and_medium'),
                I18nFallback::col('information_object_i18n', 'arrangement', 'arrangement'),
            ]);

        // This subtree is reached anonymously via public OAI-PMH / EAD export, so
        // apply the SAME gates OAI's publishedQuery uses: publication status PLUS
        // the ICIP/TK + ODRL exclusion (previously this path had only the former,
        // leaking culturally/ODRL-restricted descendants into EAD/MODS/DACS output).
        $gate = app(\AhgCore\Services\DisclosureGate::class);
        $gate->wherePublished($query, 'information_object');
        $gate->excludeRestricted($query, 'information_object.id');

        // #1388 - and drop descendants tagged with a restricted community-protocol
        // term (sacred/secret, restricted, gendered, seasonal, community-voice).
        // Fail-closed for anonymous web + CLI exports; editors bypass in the gate.
        \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($query, 'information_object.id');

        return $hierarchy->nestRows($query->get(), (int) $io->id);
    }

    protected function escXml(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function mapLevelToEad(?string $level): string
    {
        $map = [
            'Fonds' => 'fonds', 'Sub-fonds' => 'subfonds', 'Collection' => 'collection',
            'Series' => 'series', 'Sub-series' => 'subseries', 'File' => 'file',
            'Item' => 'item', 'Part' => 'item',
        ];

        return $map[$level ?? ''] ?? 'otherlevel';
    }
}
