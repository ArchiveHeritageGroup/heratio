<?php

/**
 * CidocCrmTermSerializer - CIDOC-CRM (ISO 21127) RDF serialisation of a single
 * Heratio term (subject, genre, or place access point) and its surrounding
 * context (appellation, parent/child hierarchy, the records that cite it).
 *
 * Companion to CidocCrmSerializer (records) and CidocCrmActorSerializer
 * (actors). A term is typed by its taxonomy:
 *
 *     place taxonomy (id 42)   -> crm:E53_Place
 *                                  P89 falls within  -> parent E53 Place
 *                                  P89i contains     -> child E53 Place
 *                                  P1 is identified by -> E48 Place Name
 *     subject / genre / other  -> crm:E55_Type
 *                                  P127 has broader term  -> parent E55 Type
 *                                  P127i has narrower term -> child E55 Type
 *                                  P1 is identified by -> E41 Appellation
 *
 * Records that cite the term are linked back through the inverse of the records
 * exporter's forward properties, so an term document and a record document join
 * cleanly in a triple store:
 *
 *     place  -> P67i is referred to by   -> E22 record   (records use P67 refers to)
 *     type   -> P138i has representation? no: subjects use P129 is about, so
 *               type  -> P129i is subject of -> E22 record
 *
 * Output formats: text/turtle (default) and application/rdf+xml, produced from
 * one format-neutral node bag via the shared CrmRdfRenderer trait - the same
 * rendering the records exporter uses - so the serialisations cannot drift.
 *
 * Read-only: every query is a SELECT; this class never writes the database.
 * The linked-record list is published-aware (status.type_id = 158 AND
 * status.status_id = 160; synthetic root id 1 excluded), so a term document
 * never leaks the title of an unpublished record on a public surface.
 *
 * Phase of issue #1197 (Unified G/L/A/M knowledge graph - RiC + CIDOC-CRM + KM).
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

class CidocCrmTermSerializer
{
    use CrmRdfRenderer;

    /** Publication-status gate (status table; AtoM term ids) - identical to
     *  the records exporter so linked records share the same gate. */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Place taxonomy id (AtoM "Places"). Terms in any other taxonomy are
     *  treated as E55 Type (subjects, genres, etc.). */
    private const TAXONOMY_PLACE = 42;

    public function getFormat(): string
    {
        return 'cidoc-crm-term';
    }

    /**
     * Serialise one term (subject / genre / place) as a CIDOC-CRM RDF document.
     *
     * @param int    $termId     term.id
     * @param string $culture    i18n culture for labels (default 'en')
     * @param string $format     self::FORMAT_TURTLE | self::FORMAT_RDFXML
     * @param bool   $publicOnly when true, restricts the cited-record list to
     *                            published records. The term node, its
     *                            appellation and hierarchy are always emitted.
     *
     * Returns '' when the term row is missing so callers decide skip vs 404.
     */
    public function serializeTerm(int $termId, string $culture = 'en', string $format = self::FORMAT_TURTLE, bool $publicOnly = false): string
    {
        if ($termId < 1) {
            return '';
        }

        $term = $this->fetchTerm($termId, $culture);
        if (! $term) {
            return '';
        }

        $parent   = $this->fetchTerm((int) ($term->parent_id ?? 0), $culture);
        $children = $this->fetchChildren($termId, $culture);
        $records  = $this->fetchCitingRecords($termId, $culture, $publicOnly);

        $bag = $this->buildGraph($term, $parent, $children, $records, $culture);

        return $this->render($bag, $format);
    }

    // -----------------------------------------------------------------
    // Read-only fetches (SELECT only).
    // -----------------------------------------------------------------

    private function fetchTerm(int $termId, string $culture)
    {
        if ($termId < 1) {
            return null;
        }

        return DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'term.id')
            ->where('term.id', $termId)
            ->select([
                'term.id', 'term.taxonomy_id', 'term.parent_id',
                'term_i18n.name', 's.slug',
            ])
            ->first();
    }

    private function fetchChildren(int $termId, string $culture)
    {
        return DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'term.id')
            ->where('term.parent_id', $termId)
            ->select(['term.id', 'term_i18n.name', 's.slug'])
            ->orderBy('term_i18n.name')
            ->get();
    }

    /**
     * Records that cite this term as an access point. When $publicOnly is set
     * only published records are returned.
     */
    private function fetchCitingRecords(int $termId, string $culture, bool $publicOnly)
    {
        $q = DB::table('object_term_relation as otr')
            ->join('information_object as io', 'otr.object_id', '=', 'io.id')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('otr.term_id', $termId);

        if ($publicOnly) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'io.id')
                    ->where('status.type_id', self::STATUS_TYPE_PUBLICATION)
                    ->where('status.status_id', self::PUBLICATION_STATUS_PUBLISHED);
            })->where('io.id', '>', 1);
        }

        return $q->select(['io.id', 'i18n.title', 's.slug'])->distinct()->get();
    }

    // -----------------------------------------------------------------
    // Graph construction.
    // -----------------------------------------------------------------

    private function buildGraph($term, $parent, $children, $records, string $culture): array
    {
        $isPlace = (int) ($term->taxonomy_id ?? 0) === self::TAXONOMY_PLACE;
        $termUri = $this->termUri($term);

        $nodes = [];
        $props = [];

        if (! empty($term->name)) {
            $props[] = ['rdfs:label', (string) $term->name, 'lang'];
            $appUri = $termUri . '#crm-appellation';
            $props[] = ['crm:P1_is_identified_by', $appUri, 'iri'];
        }

        // Hierarchy: P89 falls within (place) / P127 has broader term (type).
        if ($parent) {
            $parentUri = $this->termUri($parent);
            $props[] = $isPlace
                ? ['crm:P89_falls_within', $parentUri, 'iri']
                : ['crm:P127_has_broader_term', $parentUri, 'iri'];
        }
        foreach ($children as $child) {
            $childUri = $this->termUri($child);
            $props[] = $isPlace
                ? ['crm:P89i_contains', $childUri, 'iri']
                : ['crm:P127i_has_narrower_term', $childUri, 'iri'];
        }

        // Cited-by: inverse of the records exporter's forward link.
        //   place: records emit P67 refers to -> term, so term -> P67i is referred to by
        //   type:  records emit P129 is about -> term, so term -> P129i is subject of
        foreach ($records as $rec) {
            $choUri = $this->recordUrl($rec) . '#crm-object';
            $props[] = $isPlace
                ? ['crm:P67i_is_referred_to_by', $choUri, 'iri']
                : ['crm:P129i_is_subject_of', $choUri, 'iri'];
        }

        $termClass = $isPlace ? 'crm:E53_Place' : 'crm:E55_Type';
        $nodes[] = [$termUri, $termClass, $props];

        // ---- Appellation node (E48 Place Name for places, E41 Appellation otherwise) ----
        if (! empty($term->name)) {
            $appClass = $isPlace ? 'crm:E48_Place_Name' : 'crm:E41_Appellation';
            $nodes[] = [$termUri . '#crm-appellation', $appClass, [
                ['rdfs:label', (string) $term->name, 'lang'],
            ]];
        }

        // ---- Parent node (label + same class) ----
        if ($parent) {
            $parentUri = $this->termUri($parent);
            $nodes[] = [$parentUri, $termClass, [
                ['rdfs:label', (string) ($parent->name ?? ''), 'lang'],
            ]];
        }

        // ---- Child nodes (label + same class) ----
        foreach ($children as $child) {
            $childUri = $this->termUri($child);
            $nodes[] = [$childUri, $termClass, [
                ['rdfs:label', (string) ($child->name ?? ''), 'lang'],
            ]];
        }

        // ---- Cited record nodes (E22 stub: label only) ----
        foreach ($records as $rec) {
            $choUri = $this->recordUrl($rec) . '#crm-object';
            $nodes[] = [$choUri, 'crm:E22_Human-Made_Object', [
                ['rdfs:label', (string) ($rec->title ?? ''), 'lang'],
            ]];
        }

        return ['nodes' => $nodes, 'culture' => $culture];
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    private function termUri($term): string
    {
        if (! empty($term->slug)) {
            return rtrim((string) url('/'), '/') . '/term/' . $term->slug;
        }

        return rtrim((string) url('/'), '/') . '/term/' . ((int) $term->id);
    }

    private function recordUrl($rec): string
    {
        if (! empty($rec->slug)) {
            return rtrim((string) url('/'), '/') . '/' . $rec->slug;
        }

        return rtrim((string) url('/'), '/') . '/informationobject/' . ((int) $rec->id);
    }
}
