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

trait InformationObjectFetcher
{
    protected function fetchIo(int $objectId, string $culture)
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'o.id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select([
                'io.id', 'io.identifier', 'io.level_of_description_id',
                'io.repository_id', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.source_culture',
                'i18n.title', 'i18n.extent_and_medium', 'i18n.archival_history',
                'i18n.acquisition', 'i18n.scope_and_content', 'i18n.appraisal',
                'i18n.accruals', 'i18n.arrangement', 'i18n.access_conditions',
                'i18n.reproduction_conditions', 'i18n.physical_characteristics',
                'i18n.finding_aids', 'i18n.location_of_originals',
                'i18n.location_of_copies', 'i18n.related_units_of_description',
                'i18n.rules', 'i18n.sources', 'i18n.revision_history',
                'o.created_at', 'o.updated_at', 's.slug',
            ])
            ->first();
    }

    protected function fetchRepository($io, string $culture)
    {
        if (empty($io->repository_id)) {
            return null;
        }

        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('repository.id', $io->repository_id)
            ->where('actor_i18n.culture', $culture)
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }

    protected function fetchEvents($io, string $culture)
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', $culture);
            })
            ->where('event.object_id', $io->id)
            ->select('event.id', 'event.type_id', 'event.actor_id',
                'event.start_date', 'event.end_date',
                'event_i18n.date as date_display')
            ->get();
    }

    protected function fetchCreators($io, string $culture)
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('actor_i18n.authorized_form_of_name as name',
                'actor.entity_type_id', 'actor.id as actor_id')
            ->distinct()
            ->get();
    }

    protected function fetchAccessPoints($io, int $taxonomyId, string $culture)
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();
    }

    protected function fetchNotes($io, string $culture)
    {
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.type_id', 'note_i18n.content')
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
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();
    }

    protected function fetchDescendants($io, string $culture)
    {
        $query = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object.lft', '>', $io->lft)
            ->where('information_object.rgt', '<', $io->rgt)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select([
                'information_object.id', 'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.lft', 'information_object.rgt',
                'information_object_i18n.title',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.arrangement',
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

        return $query->get();
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
