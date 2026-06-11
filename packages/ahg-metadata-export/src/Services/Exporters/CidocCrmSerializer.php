<?php

/**
 * CidocCrmSerializer - CIDOC-CRM (ISO 21127) RDF serialisation of a single
 * Heratio information_object and its surrounding entities (creators, subjects,
 * places, languages, repository, dates).
 *
 * This is the metadata-export package's own CIDOC-CRM exporter, wired into the
 * same per-record download framework as the DACS / MODS / RAD / dcterms
 * serializers (MetadataExportController::downloadStandard). It complements the
 * RiC-bridge serializer in packages/ahg-ric/src/Crm/CrmSerializer.php, sharing
 * the same namespace conventions and CRM class/property vocabulary so the two
 * documents are interoperable, but additionally models an explicit
 * E12 Production event so the production chain
 *
 *     E22 object  - P108i was produced by - E12 Production
 *                   E12 - P14 carried out by - E39 Actor
 *                   E12 - P4 has time-span  - E52 Time-Span
 *
 * is expressed as first-class CRM rather than a flattened P14 on the object.
 *
 * Phase of issue #1197 (Unified G/L/A/M knowledge graph - RiC + CIDOC-CRM + KM).
 *
 * CRM class / property mapping (Erlangen-CRM compatible; default namespace is
 * the official CIDOC-CRM ns, with an `ecrm:` alias declared for Erlangen tooling):
 *
 *   information_object                 -> E22 Human-Made Object  (E24 fallback)
 *   identifier                         -> P1 is identified by -> E42 Identifier
 *   title                              -> P102 has title       -> E35 Title
 *   scope_and_content / notes          -> P3 has note
 *   extent_and_medium                  -> P43 has dimension (note literal)
 *   creator actor (event type_id 111)  -> E12 Production
 *                                          P108i was produced by E12
 *                                          E12 P14 carried out by E39 Actor
 *   repository                         -> P50 has current keeper -> E40 Legal Body
 *   subject access points (taxonomy35) -> P129 is about         -> E1 CRM Entity
 *   place access points  (taxonomy 42) -> P67 refers to / P7 (event) E53 Place
 *   language access points (taxonomy 7)-> P72 has language      -> E56 Language
 *   event start_date / end_date        -> E52 Time-Span
 *                                          P82a begin of the begin / P82b end of the end
 *
 * Output formats: text/turtle and application/rdf+xml. Both express the same
 * graph; the Turtle form is intended for human review + git diffs, the RDF/XML
 * form for CRM tooling (ResearchSpace, Erlangen-CRM importer, Apache Jena).
 *
 * Read-only: every query is a SELECT through InformationObjectFetcher; this
 * class never writes to the database. Public exposure is gated to published
 * records (status.type_id = 158 AND status.status_id = 160), with the root
 * information_object (id = 1) excluded, mirroring the browse publication gate.
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

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CidocCrmSerializer
{
    use InformationObjectFetcher;

    /** Namespaces. The default CRM ns is the official CIDOC one; the Erlangen
     *  CRM (`ecrm:`) is declared as an alias so Erlangen-based tooling resolves
     *  the same local names. */
    public const NS_RDF  = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
    public const NS_XSD  = 'http://www.w3.org/2001/XMLSchema#';
    public const NS_CRM  = 'http://www.cidoc-crm.org/cidoc-crm/';
    public const NS_ECRM = 'http://erlangen-crm.org/current/';

    /** Output formats accepted by serializeRecord(). */
    public const FORMAT_TURTLE = 'turtle';
    public const FORMAT_RDFXML = 'rdfxml';

    /** Publication-status gate (status table; AtoM term ids). */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Access-point taxonomy ids. */
    private const TAXONOMY_SUBJECT = 35;
    private const TAXONOMY_PLACE   = 42;
    private const TAXONOMY_LANGUAGE = 7;

    /** Creator event type id (AtoM "Creation"). */
    private const EVENT_TYPE_CREATION = 111;

    public function getFormat(): string
    {
        return 'cidoc-crm';
    }

    /**
     * Serialise one information_object as a CIDOC-CRM RDF document.
     *
     * @param int    $objectId information_object.id
     * @param string $culture  i18n culture for labels (default 'en')
     * @param string $format   self::FORMAT_TURTLE | self::FORMAT_RDFXML
     * @param bool   $publicOnly when true, returns '' unless the record passes
     *                            the published-records gate (used by any public
     *                            exposure). Admin callers pass false.
     *
     * Returns '' when the IO is missing (or fails the gate) so callers decide
     * whether that means "skip" or "404".
     */
    public function serializeRecord(int $objectId, string $culture = 'en', string $format = self::FORMAT_TURTLE, bool $publicOnly = false): string
    {
        if ($publicOnly && ! $this->isPublic($objectId)) {
            return '';
        }

        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $creators   = $this->fetchCreators($io, $culture);
        $events     = $this->fetchEvents($io, $culture);
        $subjects   = $this->fetchAccessPoints($io, self::TAXONOMY_SUBJECT, $culture);
        $places     = $this->fetchAccessPoints($io, self::TAXONOMY_PLACE, $culture);
        $languages  = $this->fetchAccessPoints($io, self::TAXONOMY_LANGUAGE, $culture);

        $bag = $this->buildGraph($io, $repository, $creators, $events, $subjects, $places, $languages, $culture);

        if ($format === self::FORMAT_RDFXML) {
            return $this->renderRdfXml($bag);
        }

        return $this->renderTurtle($bag);
    }

    /**
     * The published-records gate. True when a published status row exists for
     * this object and the object is not the synthetic root (id = 1).
     */
    private function isPublic(int $objectId): bool
    {
        if ($objectId <= 1) {
            return false;
        }
        if (! Schema::hasTable('status')) {
            return false;
        }

        return DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::PUBLICATION_STATUS_PUBLISHED)
            ->exists();
    }

    // -----------------------------------------------------------------
    // Graph construction - format-neutral intermediate representation.
    // Each node is [uri, type-curie, [ [predicate-curie, value, kind] ... ] ]
    // where kind is one of: 'iri' (object is a URI), 'lang' (language-tagged
    // literal), 'plain' (plain literal), 'date' (xsd:date literal).
    // -----------------------------------------------------------------

    private function buildGraph($io, $repository, $creators, $events, $subjects, $places, $languages, string $culture): array
    {
        $ioUrl  = $this->ioPublicUrl($io);
        $choUri = $ioUrl . '#crm-object';

        $nodes = [];

        // ---- Central object node (E22 Human-Made Object) ----
        $objectProps = [];
        $objectProps[] = ['rdfs:label', (string) ($io->title ?? ''), 'lang'];
        if (! empty($io->title)) {
            $titleUri = $ioUrl . '#crm-title';
            $objectProps[] = ['crm:P102_has_title', $titleUri, 'iri'];
        }
        if (! empty($io->identifier)) {
            $idUri = $ioUrl . '#crm-identifier';
            $objectProps[] = ['crm:P1_is_identified_by', $idUri, 'iri'];
        }
        if (! empty($io->scope_and_content)) {
            $objectProps[] = ['crm:P3_has_note', (string) $io->scope_and_content, 'lang'];
        }
        if (! empty($io->extent_and_medium)) {
            $objectProps[] = ['crm:P3_has_note', (string) $io->extent_and_medium, 'lang'];
        }

        // P108i was produced by -> one E12 Production per creator (or a single
        // E12 when there are dated creation events but no actor).
        $productionUri = $ioUrl . '#crm-production';
        $hasProduction = ! empty($creators);
        // Dated creation events also justify a Production node.
        $datedCreation = false;
        foreach ($events as $event) {
            if ((int) ($event->type_id ?? 0) === self::EVENT_TYPE_CREATION
                && (! empty($event->start_date) || ! empty($event->end_date) || ! empty($event->date_display))) {
                $datedCreation = true;
                break;
            }
        }
        if ($hasProduction || $datedCreation) {
            $objectProps[] = ['crm:P108i_was_produced_by', $productionUri, 'iri'];
        }

        // P50 has current keeper -> repository
        if ($repository) {
            $repoUri = $this->agentUri((int) $repository->id);
            $objectProps[] = ['crm:P50_has_current_keeper', $repoUri, 'iri'];
        }

        // P129 is about -> subjects (each becomes an E1 CRM Entity node).
        foreach ($subjects as $i => $s) {
            $subjUri = $ioUrl . '#crm-subject-' . $i;
            $objectProps[] = ['crm:P129_is_about', $subjUri, 'iri'];
        }

        // P67 refers to -> places (each becomes an E53 Place node).
        foreach ($places as $i => $p) {
            $placeUri = $ioUrl . '#crm-place-' . $i;
            $objectProps[] = ['crm:P67_refers_to', $placeUri, 'iri'];
        }

        // P72 has language -> languages (each becomes an E56 Language node).
        foreach ($languages as $i => $lang) {
            $langUri = $ioUrl . '#crm-language-' . $i;
            $objectProps[] = ['crm:P72_has_language', $langUri, 'iri'];
        }

        $nodes[] = [$choUri, 'crm:E22_Human-Made_Object', $objectProps];

        // ---- E35 Title node ----
        if (! empty($io->title)) {
            $nodes[] = [$ioUrl . '#crm-title', 'crm:E35_Title', [
                ['rdfs:label', (string) $io->title, 'lang'],
            ]];
        }

        // ---- E42 Identifier node ----
        if (! empty($io->identifier)) {
            $nodes[] = [$ioUrl . '#crm-identifier', 'crm:E42_Identifier', [
                ['rdfs:label', (string) $io->identifier, 'plain'],
            ]];
        }

        // ---- E12 Production node (the production event itself) ----
        if ($hasProduction || $datedCreation) {
            $prodProps = [];
            $prodProps[] = ['rdfs:label', 'Production of ' . (string) ($io->title ?? ('object ' . $io->id)), 'lang'];
            foreach ($creators as $creator) {
                $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
                $prodProps[] = ['crm:P14_carried_out_by', $agentUri, 'iri'];
            }
            // E12 P4 has time-span -> one E52 per dated creation event.
            foreach ($events as $event) {
                if ((int) ($event->type_id ?? 0) !== self::EVENT_TYPE_CREATION) {
                    continue;
                }
                if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                    continue;
                }
                $tsUri = $ioUrl . '#crm-timespan-' . ((int) ($event->id ?? 0));
                $prodProps[] = ['crm:P4_has_time-span', $tsUri, 'iri'];
            }
            $nodes[] = [$productionUri, 'crm:E12_Production', $prodProps];
        }

        // ---- E39 / E21 / E40 / E74 Actor nodes (creators) ----
        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $crmClass = $this->actorClassFor((int) ($creator->entity_type_id ?? 0));
            $nodes[] = [$agentUri, $crmClass, [
                ['rdfs:label', (string) $creator->name, 'lang'],
            ]];
        }

        // ---- E40 Legal Body node (repository) ----
        if ($repository) {
            $repoUri = $this->agentUri((int) $repository->id);
            $nodes[] = [$repoUri, 'crm:E40_Legal_Body', [
                ['rdfs:label', (string) $repository->name, 'lang'],
            ]];
        }

        // ---- E52 Time-Span nodes (one per dated creation event) ----
        foreach ($events as $event) {
            if ((int) ($event->type_id ?? 0) !== self::EVENT_TYPE_CREATION) {
                continue;
            }
            if (empty($event->start_date) && empty($event->end_date) && empty($event->date_display)) {
                continue;
            }
            $tsUri = $ioUrl . '#crm-timespan-' . ((int) ($event->id ?? 0));
            $tsProps = [];
            if (! empty($event->start_date)) {
                $tsProps[] = ['crm:P82a_begin_of_the_begin', (string) $event->start_date, 'date'];
            }
            if (! empty($event->end_date)) {
                $tsProps[] = ['crm:P82b_end_of_the_end', (string) $event->end_date, 'date'];
            }
            if (! empty($event->date_display)) {
                $tsProps[] = ['rdfs:label', (string) $event->date_display, 'lang'];
            }
            $nodes[] = [$tsUri, 'crm:E52_Time-Span', $tsProps];
        }

        // ---- E1 CRM Entity nodes (subjects) ----
        foreach ($subjects as $i => $s) {
            $nodes[] = [$ioUrl . '#crm-subject-' . $i, 'crm:E1_CRM_Entity', [
                ['rdfs:label', (string) $s->name, 'lang'],
            ]];
        }

        // ---- E53 Place nodes ----
        foreach ($places as $i => $p) {
            $nodes[] = [$ioUrl . '#crm-place-' . $i, 'crm:E53_Place', [
                ['rdfs:label', (string) $p->name, 'lang'],
            ]];
        }

        // ---- E56 Language nodes ----
        foreach ($languages as $i => $lang) {
            $nodes[] = [$ioUrl . '#crm-language-' . $i, 'crm:E56_Language', [
                ['rdfs:label', (string) $lang->name, 'lang'],
            ]];
        }

        return ['nodes' => $nodes, 'culture' => $culture];
    }

    // -----------------------------------------------------------------
    // Turtle output
    // -----------------------------------------------------------------

    private function renderTurtle(array $bag): string
    {
        $culture = $bag['culture'];
        $ttl  = '@prefix rdf: <' . self::NS_RDF . "> .\n";
        $ttl .= '@prefix rdfs: <' . self::NS_RDFS . "> .\n";
        $ttl .= '@prefix xsd: <' . self::NS_XSD . "> .\n";
        $ttl .= '@prefix crm: <' . self::NS_CRM . "> .\n";
        $ttl .= '@prefix ecrm: <' . self::NS_ECRM . "> .\n\n";

        foreach ($bag['nodes'] as [$uri, $typeCurie, $props]) {
            $ttl .= '<' . $uri . '> a ' . $typeCurie;
            foreach ($props as [$pred, $value, $kind]) {
                $ttl .= ' ;' . "\n" . '  ' . $pred . ' ' . $this->ttlValue($value, $kind, $culture);
            }
            $ttl .= " .\n\n";
        }

        return $ttl;
    }

    private function ttlValue(string $value, string $kind, string $culture): string
    {
        switch ($kind) {
            case 'iri':
                return '<' . $value . '>';
            case 'date':
                return '"' . addcslashes($value, "\\\"\n\r") . '"^^xsd:date';
            case 'lang':
                return '"' . addcslashes($value, "\\\"\n\r") . '"@' . $culture;
            case 'plain':
            default:
                return '"' . addcslashes($value, "\\\"\n\r") . '"';
        }
    }

    // -----------------------------------------------------------------
    // RDF/XML output
    // -----------------------------------------------------------------

    private function renderRdfXml(array $bag): string
    {
        $culture = $bag['culture'];
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF';
        $xml .= ' xmlns:rdf="' . self::NS_RDF . '"';
        $xml .= ' xmlns:rdfs="' . self::NS_RDFS . '"';
        $xml .= ' xmlns:xsd="' . self::NS_XSD . '"';
        $xml .= ' xmlns:crm="' . self::NS_CRM . '"';
        $xml .= ' xmlns:ecrm="' . self::NS_ECRM . '"';
        $xml .= '>' . "\n";

        foreach ($bag['nodes'] as [$uri, $typeCurie, $props]) {
            $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($uri) . '">' . "\n";
            $xml .= '    <rdf:type rdf:resource="' . $this->escAttr($this->expand($typeCurie)) . '"/>' . "\n";
            foreach ($props as [$pred, $value, $kind]) {
                $tag = $this->localTag($pred);
                if ($kind === 'iri') {
                    $xml .= '    <' . $tag . ' rdf:resource="' . $this->escAttr($value) . '"/>' . "\n";
                } elseif ($kind === 'date') {
                    $xml .= '    <' . $tag . ' rdf:datatype="' . $this->escAttr(self::NS_XSD . 'date') . '">'
                        . $this->escXml($value) . '</' . $tag . '>' . "\n";
                } elseif ($kind === 'lang') {
                    $xml .= '    <' . $tag . ' xml:lang="' . $this->escAttr($culture) . '">'
                        . $this->escXml($value) . '</' . $tag . '>' . "\n";
                } else {
                    $xml .= '    <' . $tag . '>' . $this->escXml($value) . '</' . $tag . '>' . "\n";
                }
            }
            $xml .= '  </rdf:Description>' . "\n";
        }

        $xml .= '</rdf:RDF>' . "\n";

        return $xml;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** Map a 'prefix:Local' CURIE used for predicates to a QName tag. The
     *  CIDOC P-numbers contain no characters illegal in an XML local name. */
    private function localTag(string $curie): string
    {
        return $curie; // 'crm:P102_has_title', 'rdfs:label' are valid QNames.
    }

    /** Expand a CURIE into an absolute IRI for rdf:type objects. */
    private function expand(string $curie): string
    {
        if (str_starts_with($curie, 'crm:')) {
            return self::NS_CRM . substr($curie, 4);
        }
        if (str_starts_with($curie, 'ecrm:')) {
            return self::NS_ECRM . substr($curie, 5);
        }
        if (str_starts_with($curie, 'rdfs:')) {
            return self::NS_RDFS . substr($curie, 5);
        }
        return $curie;
    }

    /**
     * Pick the most-specific CRM Actor class for a Heratio actor row. The
     * entity_type_id values are the fixed AtoM actor-type term ids:
     *   131 = Person, 132 = Corporate body, 133 = Family.
     * This mirrors RicToCrmMapper::AGENT_SUBCLASS so the two CIDOC-CRM
     * exporters agree on actor sub-typing. Unknown ids fall back to the
     * generic E39 Actor.
     */
    private function actorClassFor(int $entityTypeId): string
    {
        return match ($entityTypeId) {
            131 => 'crm:E21_Person',
            132 => 'crm:E40_Legal_Body',  // Corporate body
            133 => 'crm:E74_Group',       // Family -> Group
            default => 'crm:E39_Actor',
        };
    }

    private function ioPublicUrl($io): string
    {
        if (! empty($io->slug)) {
            return rtrim((string) url('/'), '/') . '/' . $io->slug;
        }

        return rtrim((string) url('/'), '/') . '/informationobject/' . ((int) $io->id);
    }

    private function agentUri(int $actorId): string
    {
        return rtrim((string) url('/'), '/') . '/actor/' . $actorId;
    }

    private function escAttr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
