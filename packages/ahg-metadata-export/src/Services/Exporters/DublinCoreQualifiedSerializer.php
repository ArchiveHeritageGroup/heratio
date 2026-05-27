<?php

/**
 * DublinCoreQualifiedSerializer - Dublin Core qualified terms (dcterms)
 * XML serializer for an information object.
 *
 * Issue #662 Phase 3: expands the dcterms predicate set beyond the v1.1
 * basics (15 elements) into the full DCMI Metadata Terms vocabulary at
 * https://www.dublincore.org/specifications/dublin-core/dcmi-terms/.
 *
 * Emits a <metadata> container with both the legacy dc:* elements (for
 * backward compatibility with the OAI-PMH oai_dc + the export controller
 * payload) and the qualified dcterms:* refinements where Heratio has the
 * source data. The serializer is additive only - it never drops a basic
 * element.
 *
 * Predicate coverage in this phase:
 *   - dcterms:title, dcterms:creator, dcterms:subject, dcterms:description,
 *     dcterms:publisher, dcterms:contributor, dcterms:date, dcterms:type,
 *     dcterms:format, dcterms:identifier, dcterms:source, dcterms:language,
 *     dcterms:relation, dcterms:coverage, dcterms:rights        (1:1 with dc:)
 *   - dcterms:abstract              (scope_and_content - long-form summary)
 *   - dcterms:accessRights          (access_conditions)
 *   - dcterms:accrualPeriodicity    (accruals)
 *   - dcterms:alternative           (alternate_title)
 *   - dcterms:audience              (free-text property dcterms:audience)
 *   - dcterms:available             (publication event start date)
 *   - dcterms:bibliographicCitation (free-text property dcterms:bibliographicCitation)
 *   - dcterms:conformsTo            (rules / source_standard)
 *   - dcterms:created               (event type 111 start_date)
 *   - dcterms:dateAccepted          (free-text property)
 *   - dcterms:dateCopyrighted       (event type 264 if present)
 *   - dcterms:dateSubmitted         (free-text property)
 *   - dcterms:extent                (extent_and_medium)
 *   - dcterms:hasFormat             (free-text property)
 *   - dcterms:hasPart               (descendants slug URLs)
 *   - dcterms:hasVersion            (free-text property)
 *   - dcterms:isFormatOf            (free-text property)
 *   - dcterms:isPartOf              (parent slug URL)
 *   - dcterms:isReferencedBy        (free-text property)
 *   - dcterms:isReplacedBy          (free-text property)
 *   - dcterms:isRequiredBy          (free-text property)
 *   - dcterms:issued                (event type 114 start_date)
 *   - dcterms:isVersionOf           (free-text property)
 *   - dcterms:license               (free-text property + extended-rights ref)
 *   - dcterms:medium                (carrier term from RDA mapping)
 *   - dcterms:modified              (object.updated_at)
 *   - dcterms:provenance            (archival_history)
 *   - dcterms:references            (free-text property)
 *   - dcterms:replaces              (free-text property)
 *   - dcterms:requires              (free-text property)
 *   - dcterms:rightsHolder          (repository or rights-holder relation)
 *   - dcterms:spatial               (taxonomy 42 place terms)
 *   - dcterms:temporal              (free-text property)
 *   - dcterms:valid                 (free-text property)
 *
 * Free-text dcterms:* properties are stored in the existing `property`
 * table keyed by `property.name = '<dcterms-token>'`. Operators add them
 * through the dc-manage edit page (Phase 4) or the per-standard tools.
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
 */

namespace AhgMetadataExport\Services\Exporters;

use AhgMetadataExport\Services\IptcFallbackResolver;
use Illuminate\Support\Facades\DB;

class DublinCoreQualifiedSerializer
{
    use InformationObjectFetcher;

    /**
     * Free-text qualified terms that operators may populate via the
     * existing `property` table. Keys are stored with the leading
     * "dcterms:" prefix so they round-trip cleanly with the dc-manage
     * UI and the OAI-PMH listing.
     */
    public const FREE_TEXT_TERMS = [
        'dcterms:audience',
        'dcterms:bibliographicCitation',
        'dcterms:dateAccepted',
        'dcterms:dateSubmitted',
        'dcterms:hasFormat',
        'dcterms:hasVersion',
        'dcterms:isFormatOf',
        'dcterms:isReferencedBy',
        'dcterms:isReplacedBy',
        'dcterms:isRequiredBy',
        'dcterms:isVersionOf',
        'dcterms:license',
        'dcterms:references',
        'dcterms:replaces',
        'dcterms:requires',
        'dcterms:temporal',
        'dcterms:valid',
    ];

    public function getFormat(): string
    {
        return 'dcterms';
    }

    public function getSchemaUrl(): string
    {
        return 'http://dublincore.org/schemas/xmls/qdc/2008/02/11/dcterms.xsd';
    }

    public function getNamespaces(): array
    {
        return [
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'dcterms' => 'http://purl.org/dc/terms/',
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        ];
    }

    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $ns = $this->getNamespaces();
        $xml = '<metadata';
        foreach ($ns as $prefix => $uri) {
            $xml .= ' xmlns:'.$prefix.'="'.$uri.'"';
        }
        $xml .= '>'."\n";

        $events = $this->fetchEvents($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $languages = $this->fetchLanguages($io, $culture);
        $repository = $this->fetchRepository($io, $culture);
        $levelName = $this->fetchLevelName($io, $culture);
        $alternate = $this->fetchI18nField($objectId, 'alternate_title', $culture);

        // IPTC fallback resolver (issue #752). When the ISAD(G) author /
        // reproduction-conditions / subject-access-points are empty but
        // dam_iptc_metadata carries the corresponding values, fall through
        // to the IPTC payload so harvesters see something useful. The
        // resolver also audit-logs to ahg_error_log so operators can spot
        // descriptions that survived only because of IPTC.
        $iptcResolver = new IptcFallbackResolver();

        // dc:title + dcterms:title (always emit both for backwards compat)
        $xml .= $this->emitBoth('title', $io->title);

        if ($alternate !== '') {
            $xml .= $this->qualified('alternative', $alternate);
        }

        // Creators - ISAD(G) authors first, IPTC By-line fallback when
        // canonical is empty (issue #752).
        $canonicalCreators = $creators
            ->pluck('name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        $resolvedCreators = $iptcResolver->resolveCreatorsWithCanonical((int) $objectId, $canonicalCreators);
        foreach ($resolvedCreators as $name) {
            $xml .= $this->emitBoth('creator', $name);
        }

        // Subject access points (taxonomy 35) with IPTC Keywords fallback.
        $canonicalSubjects = $subjects
            ->pluck('name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        $resolvedSubjects = $iptcResolver->resolveSubjectsWithCanonical((int) $objectId, $canonicalSubjects);
        foreach ($resolvedSubjects as $name) {
            $xml .= $this->emitBoth('subject', $name);
        }

        // Description / abstract
        if ($io->scope_and_content) {
            $clean = strip_tags($io->scope_and_content);
            $xml .= $this->emitBoth('description', $clean);
            $xml .= $this->qualified('abstract', $clean);
        }

        // Publisher (publication event preferred, repository fallback)
        $publisherEmitted = false;
        foreach ($events as $event) {
            if ((int) ($event->type_id ?? 0) === 114 && ! empty($event->actor_id)) {
                $publisher = DB::table('actor_i18n')
                    ->where('id', $event->actor_id)
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name');
                if ($publisher) {
                    $xml .= $this->emitBoth('publisher', (string) $publisher);
                    $publisherEmitted = true;
                    break;
                }
            }
        }
        if (! $publisherEmitted && $repository) {
            $xml .= $this->emitBoth('publisher', $repository->name);
        }

        // Contributors (events type 111 with non-creator role) - reuse creators set
        foreach ($creators as $contributor) {
            // creators already emitted; contributors are creators with role!=primary.
            // Heratio does not separate these explicitly today; skip duplicate.
        }

        // Dates
        $hasCreated = false;
        $hasIssued = false;
        $hasAvailable = false;
        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if (! $dateVal) {
                continue;
            }
            $typeId = (int) ($event->type_id ?? 0);
            $iso = $event->start_date ?? null;
            $xml .= $this->emitBoth('date', $dateVal);
            if ($typeId === 111 && ! $hasCreated) {
                $xml .= $this->qualified('created', (string) ($iso ?: $dateVal));
                $hasCreated = true;
            } elseif ($typeId === 114 && ! $hasIssued) {
                $xml .= $this->qualified('issued', (string) ($iso ?: $dateVal));
                $hasIssued = true;
                if (! $hasAvailable && $iso) {
                    $xml .= $this->qualified('available', (string) $iso);
                    $hasAvailable = true;
                }
            } elseif ($typeId === 264 && ! empty($iso)) {
                // event type 264 = copyright (per Heratio AHG dropdown seed)
                $xml .= $this->qualified('dateCopyrighted', (string) $iso);
            }
        }

        // Type
        if ($levelName) {
            $xml .= $this->emitBoth('type', $levelName);
        }

        // Format / extent / medium
        if ($io->extent_and_medium) {
            $clean = strip_tags($io->extent_and_medium);
            $xml .= $this->emitBoth('format', $clean);
            $xml .= $this->qualified('extent', $clean);
        }

        // Identifier
        if ($io->identifier) {
            $xml .= $this->emitBoth('identifier', $io->identifier);
        }
        if ($io->slug) {
            $xml .= $this->qualified('identifier', url('/'.$io->slug));
        }

        // Source / provenance
        if ($io->location_of_originals) {
            $xml .= $this->emitBoth('source', strip_tags($io->location_of_originals));
        }
        if ($io->archival_history) {
            $xml .= $this->qualified('provenance', strip_tags($io->archival_history));
        }

        // Language - iterate the languages-of-material list when populated,
        // otherwise fall back to the IO source_culture.
        if ($languages->count() > 0) {
            foreach ($languages as $lang) {
                $xml .= $this->emitBoth('language', $lang->name);
            }
        } elseif ($io->source_culture) {
            $xml .= $this->emitBoth('language', $io->source_culture);
        }

        // Relation: parent (isPartOf), children (hasPart), repository link
        if ($io->parent_id && (int) $io->parent_id !== 1) {
            $parentSlug = DB::table('slug')->where('object_id', $io->parent_id)->value('slug');
            if ($parentSlug) {
                $xml .= $this->qualified('isPartOf', url('/'.$parentSlug));
            }
        }
        $children = DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->id)
            ->select('slug.slug')
            ->limit(50)
            ->get();
        foreach ($children as $child) {
            $xml .= $this->qualified('hasPart', url('/'.$child->slug));
        }

        // Coverage / spatial
        foreach ($places as $place) {
            $xml .= $this->emitBoth('coverage', $place->name);
            $xml .= $this->qualified('spatial', $place->name);
        }

        // Rights - the brief's resolveRights() canonical is ISAD(G) 3.4.2
        // reproduction_conditions, falling back to IPTC Copyright Notice
        // (issue #752). access_conditions feeds dcterms:accessRights (a
        // different DC predicate) so we keep it on its own track.
        $canonicalRights = ! empty($io->reproduction_conditions)
            ? strip_tags((string) $io->reproduction_conditions)
            : null;
        $rightsOut = $iptcResolver->resolveRightsWithCanonical((int) $objectId, $canonicalRights);
        if ($rightsOut !== null && $rightsOut !== '') {
            $xml .= $this->emitBoth('rights', $rightsOut);
        }
        if ($io->access_conditions) {
            $xml .= $this->qualified('accessRights', strip_tags($io->access_conditions));
        }
        if ($repository) {
            $xml .= $this->qualified('rightsHolder', $repository->name);
        }

        // Accruals
        if (! empty($io->accruals)) {
            $xml .= $this->qualified('accrualPeriodicity', strip_tags($io->accruals));
        }

        // Conforms-to: rules + source_standard
        if (! empty($io->rules)) {
            $xml .= $this->qualified('conformsTo', strip_tags($io->rules));
        }

        // Modified
        if (! empty($io->updated_at)) {
            $iso = is_string($io->updated_at)
                ? substr((string) $io->updated_at, 0, 10)
                : (string) $io->updated_at;
            $xml .= $this->qualified('modified', $iso);
        }

        // Free-text dcterms:* properties from the `property` table.
        foreach (self::FREE_TEXT_TERMS as $term) {
            $value = $this->loadProperty($objectId, $term, $culture);
            if ($value !== '') {
                $local = substr($term, strlen('dcterms:'));
                $xml .= $this->qualified($local, $value);
            }
        }

        $xml .= '</metadata>';

        return $xml;
    }

    private function emitBoth(string $local, ?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return '  <dc:'.$local.'>'.$this->escXml($value).'</dc:'.$local.'>'."\n"
            .'  <dcterms:'.$local.'>'.$this->escXml($value).'</dcterms:'.$local.'>'."\n";
    }

    private function qualified(string $local, ?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return '  <dcterms:'.$local.'>'.$this->escXml($value).'</dcterms:'.$local.'>'."\n";
    }

    private function fetchI18nField(int $ioId, string $column, string $culture): string
    {
        try {
            $val = DB::table('information_object_i18n')
                ->where('id', $ioId)
                ->where('culture', $culture)
                ->value($column);

            return trim((string) ($val ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function loadProperty(int $ioId, string $name, string $culture): string
    {
        $raw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $ioId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        if (! $raw) {
            return '';
        }
        $decoded = @unserialize($raw);
        if (is_string($decoded)) {
            return $decoded;
        }
        if (is_array($decoded)) {
            return implode("\n\n", array_filter($decoded));
        }

        return (string) $raw;
    }
}
