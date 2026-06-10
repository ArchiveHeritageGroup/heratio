<?php

/**
 * CrmSerializer - Emits one CIDOC-CRM 7.1.3 RDF document per Heratio
 * Information Object, bridged through RicToCrmMapper.
 *
 * Output formats:
 *   - application/rdf+xml  (default; suitable for Fuseki POST + most
 *                           CIDOC tooling including ResearchSpace,
 *                           Erlangen-CRM importer, etc.)
 *   - text/turtle          (compact form for human review + git diffs)
 *   - application/ld+json  (JSON-LD; same graph as the RDF/XML form,
 *                           emitted as an @graph of typed nodes with a
 *                           crm/rico/rdfs @context)
 *
 * serializeRecord() also accepts an optional CRM record-class override
 * (CURIE, e.g. 'crm:E22_Human-Made_Object') so callers such as
 * ahg-museum can type the central node as a human-made object rather
 * than the archival default E73_Information_Object. When omitted the
 * class falls back to the rico:Record -> E73 mapping.
 *
 * The serializer shape mirrors EdmSerializer from
 * packages/ahg-federation/src/Edm/ - same DB-fetch helpers, same
 * URL/URI conventions, same defensive empty-string-on-miss return.
 * Persistence + bulk-export pipelines stay out of this class; a
 * thin controller wraps it for per-record export.
 *
 * Phase 1 of issue #659 (CIDOC-CRM v7 - class/property completeness +
 * RiC <-> CRM bridge serialiser).
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

namespace AhgRic\Crm;

use Illuminate\Support\Facades\DB;

class CrmSerializer
{
    public const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
    public const NS_XSD = 'http://www.w3.org/2001/XMLSchema#';
    public const NS_CRM = 'http://www.cidoc-crm.org/cidoc-crm/';
    public const NS_RIC = 'https://www.ica.org/standards/RiC/ontology#';

    /** Output formats accepted by serializeRecord(). */
    public const FORMAT_RDFXML = 'rdfxml';
    public const FORMAT_TURTLE = 'turtle';
    public const FORMAT_JSONLD = 'jsonld';

    /**
     * Serialise one Information Object as a CIDOC-CRM RDF document.
     *
     * Returns an empty string when the IO is missing in the supplied
     * culture - callers (controller, tests, bulk-export) decide
     * whether empty means "skip" or "404".
     */
    public function serializeRecord(int $objectId, string $culture = 'en', string $format = self::FORMAT_RDFXML, ?string $recordClass = null): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $events = $this->fetchEvents($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $languages = $this->fetchLanguages($io, $culture);

        $ioUrl = $this->ioPublicUrl($io);
        $choUri = $ioUrl . '#crm-cho';

        // Resolve the central node's CRM class. Callers may override the
        // archival default (E73 via rico:Record) - e.g. ahg-museum passes
        // 'crm:E22_Human-Made_Object' so the record types as the artefact.
        $crmRecord = $recordClass ?: RicToCrmMapper::classFor('rico:Record');

        if ($format === self::FORMAT_TURTLE) {
            return $this->renderTurtle($io, $choUri, $ioUrl, $repository, $creators, $events, $subjects, $places, $languages, $culture, $crmRecord);
        }
        if ($format === self::FORMAT_JSONLD) {
            return $this->renderJsonLd($io, $choUri, $ioUrl, $repository, $creators, $events, $subjects, $places, $languages, $culture, $crmRecord);
        }
        return $this->renderRdfXml($io, $choUri, $ioUrl, $repository, $creators, $events, $subjects, $places, $languages, $culture, $crmRecord);
    }

    // -----------------------------------------------------------------
    // RDF/XML output
    // -----------------------------------------------------------------

    protected function renderRdfXml($io, string $choUri, string $ioUrl, $repository, array $creators, array $events, array $subjects, array $places, array $languages, string $culture, ?string $crmRecord = null): string
    {
        $crmRecord = $crmRecord ?: RicToCrmMapper::classFor('rico:Record');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF';
        $xml .= ' xmlns:rdf="' . self::NS_RDF . '"';
        $xml .= ' xmlns:rdfs="' . self::NS_RDFS . '"';
        $xml .= ' xmlns:xsd="' . self::NS_XSD . '"';
        $xml .= ' xmlns:crm="' . self::NS_CRM . '"';
        $xml .= ' xmlns:rico="' . self::NS_RIC . '"';
        $xml .= '>' . "\n";

        // --------- Central CHO node (the record / object itself) ------
        $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($choUri) . '">' . "\n";
        $xml .= '    <rdf:type rdf:resource="' . $this->escAttr(RicToCrmMapper::expand($crmRecord)) . '"/>' . "\n";
        $xml .= '    <rdfs:label xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) ($io->title ?? '')) . '</rdfs:label>' . "\n";

        // crm:P102_has_title - title carried as a literal for downstream
        // consumers that prefer it as a property over rdfs:label.
        $xml .= '    <crm:P102_has_title xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) ($io->title ?? '')) . '</crm:P102_has_title>' . "\n";

        if (! empty($io->identifier)) {
            $xml .= '    <crm:P1_is_identified_by>' . $this->escXml((string) $io->identifier) . '</crm:P1_is_identified_by>' . "\n";
        }
        if (! empty($io->scope_and_content)) {
            $xml .= '    <crm:P3_has_note xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $io->scope_and_content) . '</crm:P3_has_note>' . "\n";
        }

        // P14_carried_out_by - one per creator actor.
        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $xml .= '    <crm:P14_carried_out_by rdf:resource="' . $this->escAttr($agentUri) . '"/>' . "\n";
        }

        // P4_has_time-span - one anchor per event with a date.
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $tsUri = $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0));
            $xml .= '    <crm:P4_has_time-span rdf:resource="' . $this->escAttr($tsUri) . '"/>' . "\n";
        }

        // P7_took_place_at - subject + spatial access points.
        foreach ($places as $p) {
            $xml .= '    <crm:P7_took_place_at xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $p->name) . '</crm:P7_took_place_at>' . "\n";
        }

        // P129_is_about - subject access points (taxonomy 35).
        foreach ($subjects as $s) {
            $xml .= '    <crm:P129_is_about xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $s->name) . '</crm:P129_is_about>' . "\n";
        }

        // P72_has_language - language access points (taxonomy 7).
        foreach ($languages as $lang) {
            $xml .= '    <crm:P72_has_language xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $lang->name) . '</crm:P72_has_language>' . "\n";
        }

        // P50_has_current_keeper - the repository (custodial body).
        if ($repository) {
            $repoUri = $this->agentUri((int) $repository->id);
            $xml .= '    <crm:P50_has_current_keeper rdf:resource="' . $this->escAttr($repoUri) . '"/>' . "\n";
        }

        // owl:sameAs back to RiC IRI so consumers can round-trip the
        // graph - omitted to keep the bridge unilateral; documented
        // in the reference doc as a Phase 2 reciprocal property.

        $xml .= '  </rdf:Description>' . "\n";

        // --------- E39 / E21 / E40 / E74 Actor nodes ---------
        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $crmClass = RicToCrmMapper::agentClassFor((int) ($creator->entity_type_id ?? 0));
            $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($agentUri) . '">' . "\n";
            $xml .= '    <rdf:type rdf:resource="' . $this->escAttr(RicToCrmMapper::expand($crmClass)) . '"/>' . "\n";
            $xml .= '    <rdfs:label xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $creator->name) . '</rdfs:label>' . "\n";
            $xml .= '  </rdf:Description>' . "\n";
        }

        if ($repository) {
            $repoUri = $this->agentUri((int) $repository->id);
            $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($repoUri) . '">' . "\n";
            $xml .= '    <rdf:type rdf:resource="' . $this->escAttr(RicToCrmMapper::expand('crm:E40_Legal_Body')) . '"/>' . "\n";
            $xml .= '    <rdfs:label xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $repository->name) . '</rdfs:label>' . "\n";
            $xml .= '  </rdf:Description>' . "\n";
        }

        // --------- E52 Time-Span nodes (one per dated event) ---------
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $tsUri = $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0));
            $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($tsUri) . '">' . "\n";
            $xml .= '    <rdf:type rdf:resource="' . $this->escAttr(RicToCrmMapper::expand('crm:E52_Time-Span')) . '"/>' . "\n";
            if (! empty($event->start_date)) {
                $xml .= '    <crm:P82a_begin_of_the_begin rdf:datatype="' . $this->escAttr(self::NS_XSD . 'date') . '">' . $this->escXml((string) $event->start_date) . '</crm:P82a_begin_of_the_begin>' . "\n";
            }
            if (! empty($event->end_date)) {
                $xml .= '    <crm:P82b_end_of_the_end rdf:datatype="' . $this->escAttr(self::NS_XSD . 'date') . '">' . $this->escXml((string) $event->end_date) . '</crm:P82b_end_of_the_end>' . "\n";
            }
            if (! empty($event->date_display)) {
                $xml .= '    <rdfs:label xml:lang="' . $this->escAttr($culture) . '">' . $this->escXml((string) $event->date_display) . '</rdfs:label>' . "\n";
            }
            $xml .= '  </rdf:Description>' . "\n";
        }

        $xml .= '</rdf:RDF>' . "\n";
        return $xml;
    }

    // -----------------------------------------------------------------
    // Turtle output - same semantics, compact serialisation
    // -----------------------------------------------------------------

    protected function renderTurtle($io, string $choUri, string $ioUrl, $repository, array $creators, array $events, array $subjects, array $places, array $languages, string $culture, ?string $crmRecord = null): string
    {
        $crmRecord = $crmRecord ?: RicToCrmMapper::classFor('rico:Record');
        $ttl = '@prefix rdf: <' . self::NS_RDF . "> .\n";
        $ttl .= '@prefix rdfs: <' . self::NS_RDFS . "> .\n";
        $ttl .= '@prefix xsd: <' . self::NS_XSD . "> .\n";
        $ttl .= '@prefix crm: <' . self::NS_CRM . "> .\n";
        $ttl .= '@prefix rico: <' . self::NS_RIC . "> .\n\n";

        $ttl .= '<' . $choUri . '> a <' . RicToCrmMapper::expand($crmRecord) . '> ;' . "\n";
        $ttl .= '  rdfs:label ' . $this->ttlString((string) ($io->title ?? ''), $culture) . ' ;' . "\n";
        $ttl .= '  crm:P102_has_title ' . $this->ttlString((string) ($io->title ?? ''), $culture);
        if (! empty($io->identifier)) {
            $ttl .= ' ;' . "\n" . '  crm:P1_is_identified_by ' . $this->ttlString((string) $io->identifier);
        }
        if (! empty($io->scope_and_content)) {
            $ttl .= ' ;' . "\n" . '  crm:P3_has_note ' . $this->ttlString((string) $io->scope_and_content, $culture);
        }
        foreach ($creators as $creator) {
            $ttl .= ' ;' . "\n" . '  crm:P14_carried_out_by <' . $this->agentUri((int) ($creator->actor_id ?? 0)) . '>';
        }
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $ttl .= ' ;' . "\n" . '  crm:P4_has_time-span <' . $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0)) . '>';
        }
        foreach ($subjects as $s) {
            $ttl .= ' ;' . "\n" . '  crm:P129_is_about ' . $this->ttlString((string) $s->name, $culture);
        }
        foreach ($places as $p) {
            $ttl .= ' ;' . "\n" . '  crm:P7_took_place_at ' . $this->ttlString((string) $p->name, $culture);
        }
        foreach ($languages as $lang) {
            $ttl .= ' ;' . "\n" . '  crm:P72_has_language ' . $this->ttlString((string) $lang->name, $culture);
        }
        if ($repository) {
            $ttl .= ' ;' . "\n" . '  crm:P50_has_current_keeper <' . $this->agentUri((int) $repository->id) . '>';
        }
        $ttl .= " .\n\n";

        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $crmClass = RicToCrmMapper::agentClassFor((int) ($creator->entity_type_id ?? 0));
            $ttl .= '<' . $agentUri . '> a <' . RicToCrmMapper::expand($crmClass) . '> ;' . "\n";
            $ttl .= '  rdfs:label ' . $this->ttlString((string) $creator->name, $culture) . " .\n\n";
        }
        if ($repository) {
            $repoUri = $this->agentUri((int) $repository->id);
            $ttl .= '<' . $repoUri . '> a <' . RicToCrmMapper::expand('crm:E40_Legal_Body') . '> ;' . "\n";
            $ttl .= '  rdfs:label ' . $this->ttlString((string) $repository->name, $culture) . " .\n\n";
        }
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $tsUri = $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0));
            $ttl .= '<' . $tsUri . '> a <' . RicToCrmMapper::expand('crm:E52_Time-Span') . '>';
            if (! empty($event->start_date)) {
                $ttl .= ' ;' . "\n" . '  crm:P82a_begin_of_the_begin "' . addslashes((string) $event->start_date) . '"^^xsd:date';
            }
            if (! empty($event->end_date)) {
                $ttl .= ' ;' . "\n" . '  crm:P82b_end_of_the_end "' . addslashes((string) $event->end_date) . '"^^xsd:date';
            }
            if (! empty($event->date_display)) {
                $ttl .= ' ;' . "\n" . '  rdfs:label ' . $this->ttlString((string) $event->date_display, $culture);
            }
            $ttl .= " .\n\n";
        }

        return $ttl;
    }

    // -----------------------------------------------------------------
    // JSON-LD output - same graph as RDF/XML, expressed as an @graph of
    // typed nodes. CURIEs are expanded against an @context so the result
    // is valid JSON-LD 1.1 consumable by jsonld.js, Apache Jena and
    // ResearchSpace alike.
    // -----------------------------------------------------------------

    protected function renderJsonLd($io, string $choUri, string $ioUrl, $repository, array $creators, array $events, array $subjects, array $places, array $languages, string $culture, ?string $crmRecord = null): string
    {
        $crmRecord = $crmRecord ?: RicToCrmMapper::classFor('rico:Record');

        $context = [
            'rdf'  => self::NS_RDF,
            'rdfs' => self::NS_RDFS,
            'xsd'  => self::NS_XSD,
            'crm'  => self::NS_CRM,
            'rico' => self::NS_RIC,
        ];

        $graph = [];

        // --------- Central CHO node ---------
        $record = [
            '@id'   => $choUri,
            '@type' => $crmRecord,
            'rdfs:label'         => $this->jsonLangLiteral((string) ($io->title ?? ''), $culture),
            'crm:P102_has_title' => $this->jsonLangLiteral((string) ($io->title ?? ''), $culture),
        ];
        if (! empty($io->identifier)) {
            $record['crm:P1_is_identified_by'] = (string) $io->identifier;
        }
        if (! empty($io->scope_and_content)) {
            $record['crm:P3_has_note'] = $this->jsonLangLiteral((string) $io->scope_and_content, $culture);
        }

        $carriedOutBy = [];
        foreach ($creators as $creator) {
            $carriedOutBy[] = ['@id' => $this->agentUri((int) ($creator->actor_id ?? 0))];
        }
        if ($carriedOutBy) {
            $record['crm:P14_carried_out_by'] = $carriedOutBy;
        }

        $timeSpans = [];
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $timeSpans[] = ['@id' => $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0))];
        }
        if ($timeSpans) {
            $record['crm:P4_has_time-span'] = $timeSpans;
        }

        if ($places) {
            $record['crm:P7_took_place_at'] = array_map(fn ($p) => $this->jsonLangLiteral((string) $p->name, $culture), $places);
        }
        if ($subjects) {
            $record['crm:P129_is_about'] = array_map(fn ($s) => $this->jsonLangLiteral((string) $s->name, $culture), $subjects);
        }
        if ($languages) {
            $record['crm:P72_has_language'] = array_map(fn ($l) => $this->jsonLangLiteral((string) $l->name, $culture), $languages);
        }
        if ($repository) {
            $record['crm:P50_has_current_keeper'] = ['@id' => $this->agentUri((int) $repository->id)];
        }
        $graph[] = $record;

        // --------- Actor nodes ---------
        foreach ($creators as $creator) {
            $graph[] = [
                '@id'        => $this->agentUri((int) ($creator->actor_id ?? 0)),
                '@type'      => RicToCrmMapper::agentClassFor((int) ($creator->entity_type_id ?? 0)),
                'rdfs:label' => $this->jsonLangLiteral((string) $creator->name, $culture),
            ];
        }
        if ($repository) {
            $graph[] = [
                '@id'        => $this->agentUri((int) $repository->id),
                '@type'      => 'crm:E40_Legal_Body',
                'rdfs:label' => $this->jsonLangLiteral((string) $repository->name, $culture),
            ];
        }

        // --------- Time-Span nodes ---------
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $node = [
                '@id'   => $ioUrl . '#crm-ts-' . ((int) ($event->id ?? 0)),
                '@type' => 'crm:E52_Time-Span',
            ];
            if (! empty($event->start_date)) {
                $node['crm:P82a_begin_of_the_begin'] = ['@value' => (string) $event->start_date, '@type' => 'xsd:date'];
            }
            if (! empty($event->end_date)) {
                $node['crm:P82b_end_of_the_end'] = ['@value' => (string) $event->end_date, '@type' => 'xsd:date'];
            }
            if (! empty($event->date_display)) {
                $node['rdfs:label'] = $this->jsonLangLiteral((string) $event->date_display, $culture);
            }
            $graph[] = $node;
        }

        return json_encode([
            '@context' => $context,
            '@graph'   => $graph,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A JSON-LD language-tagged literal {@value, @language}. Falls back
     * to a bare {@value} when no culture is supplied.
     */
    protected function jsonLangLiteral(string $value, ?string $lang = null): array
    {
        $literal = ['@value' => $value];
        if ($lang) {
            $literal['@language'] = $lang;
        }
        return $literal;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function ioPublicUrl($io): string
    {
        if (! empty($io->slug)) {
            return rtrim((string) url('/'), '/') . '/' . $io->slug;
        }
        return rtrim((string) url('/'), '/') . '/informationobject/' . ((int) $io->id);
    }

    protected function agentUri(int $actorId): string
    {
        return rtrim((string) url('/'), '/') . '/actor/' . $actorId;
    }

    protected function fetchIo(int $objectId, string $culture)
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select([
                'io.id', 'io.identifier', 'io.repository_id',
                'io.source_culture',
                'i18n.title', 'i18n.scope_and_content',
                'i18n.extent_and_medium', 'i18n.access_conditions',
                's.slug',
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

    protected function fetchCreators($io, string $culture): array
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select(
                'actor_i18n.authorized_form_of_name as name',
                'actor.entity_type_id',
                'actor.id as actor_id'
            )
            ->distinct()
            ->get()
            ->all();
    }

    protected function fetchEvents($io, string $culture): array
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', $culture);
            })
            ->where('event.object_id', $io->id)
            ->select(
                'event.id', 'event.type_id', 'event.actor_id',
                'event.start_date', 'event.end_date',
                'event_i18n.date as date_display'
            )
            ->get()
            ->all();
    }

    protected function fetchAccessPoints($io, int $taxonomyId, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }

    protected function fetchLanguages($io, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }

    protected function ttlString(string $value, ?string $lang = null): string
    {
        // Escape inner quotes + backslashes per Turtle string literal rules.
        $escaped = addcslashes($value, "\\\"\n\r");
        if ($lang) {
            return '"' . $escaped . '"@' . $lang;
        }
        return '"' . $escaped . '"';
    }

    protected function escXml(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function escAttr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
