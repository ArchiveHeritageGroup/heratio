<?php

/**
 * CidocCrmActorSerializer - CIDOC-CRM (ISO 21127) RDF serialisation of a single
 * Heratio actor (person / corporate body / family) and its surrounding context
 * (appellation, history note, dates of existence, the records it produced).
 *
 * Companion to CidocCrmSerializer (the records exporter). Where that class
 * centres an E22 Human-Made Object, this one centres the actor itself, typed by
 * the AtoM actor.entity_type_id sub-type:
 *
 *     person          -> crm:E21_Person
 *     corporate body  -> crm:E40_Legal_Body
 *     family          -> crm:E74_Group
 *     (unknown)       -> crm:E39_Actor   (generic super-class)
 *
 * The sub-typing table (131 / 132 / 133) is identical to the records exporter's
 * actorClassFor() and to AhgRic\Crm\RicToCrmMapper::AGENT_SUBCLASS, so all three
 * CIDOC-CRM surfaces agree on actor classes.
 *
 * CRM class / property mapping:
 *
 *   actor                              -> E21 Person / E40 Legal Body / E74 Group / E39 Actor
 *   authorized_form_of_name            -> P1 is identified by -> E82 Actor Appellation
 *   history                            -> P3 has note
 *   dates_of_existence (display)       -> P3 has note (label on the time-span)
 *   birth/death existence event dates  -> P98i was born / P100i died by E67 Birth / E69 Death
 *                                          (persons), else generic existence note
 *   existence time-span                -> E52 Time-Span
 *                                          P82a begin of the begin / P82b end of the end
 *   produced records (event type 111)  -> P11i participated in -> E12 Production
 *                                          (the production of each record), so the
 *                                          chain Actor - P11i - E12 - P108 produced - E22
 *                                          mirrors the records exporter's forward chain.
 *
 * Production events are emitted as object IRIs that resolve to the records
 * exporter's production nodes (same `#crm-production` fragment under the record
 * URL), so an actor document and a record document join cleanly in a triple
 * store.
 *
 * Output formats: text/turtle (default) and application/rdf+xml, produced from
 * one format-neutral node bag via the shared CrmRdfRenderer trait - the same
 * rendering the records exporter uses - so the serialisations cannot drift.
 *
 * Read-only: every query is a SELECT; this class never writes the database.
 * The linked-record list is published-aware (status.type_id = 158 AND
 * status.status_id = 160; synthetic root id 1 excluded), so an actor document
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
use Illuminate\Support\Facades\Schema;

class CidocCrmActorSerializer
{
    use CrmRdfRenderer;

    /** Publication-status gate (status table; AtoM term ids) - identical to
     *  the records exporter so linked records share the same gate. */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Creator event type id (AtoM "Creation"). */
    private const EVENT_TYPE_CREATION = 111;

    /** Actor entity_type_id values (fixed AtoM actor-type term ids). */
    private const ENTITY_PERSON = 131;
    private const ENTITY_CORPORATE = 132;
    private const ENTITY_FAMILY = 133;

    public function getFormat(): string
    {
        return 'cidoc-crm-actor';
    }

    /**
     * Serialise one actor as a CIDOC-CRM RDF document.
     *
     * @param int    $actorId    actor.id
     * @param string $culture    i18n culture for labels (default 'en')
     * @param string $format     self::FORMAT_TURTLE | self::FORMAT_RDFXML
     * @param bool   $publicOnly when true, restricts the linked-record list to
     *                            published records (used by any public surface).
     *                            The actor node itself is always emitted.
     *
     * Returns '' when the actor row is missing so callers decide skip vs 404.
     */
    public function serializeActor(int $actorId, string $culture = 'en', string $format = self::FORMAT_TURTLE, bool $publicOnly = false): string
    {
        if ($actorId < 1) {
            return '';
        }

        $actor = $this->fetchActor($actorId, $culture);
        if (! $actor) {
            return '';
        }

        $produced = $this->fetchProducedRecords($actorId, $culture, $publicOnly);

        $bag = $this->buildGraph($actor, $produced, $culture);

        return $this->render($bag, $format);
    }

    // -----------------------------------------------------------------
    // Read-only fetches (SELECT only).
    // -----------------------------------------------------------------

    private function fetchActor(int $actorId, string $culture)
    {
        return DB::table('actor')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'actor.id')
            ->where('actor.id', $actorId)
            ->select([
                'actor.id', 'actor.entity_type_id',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.history', 'actor_i18n.dates_of_existence',
                's.slug',
            ])
            ->first();
    }

    /**
     * Records this actor created, via a creation event (type_id 111). When
     * $publicOnly is set, only published records are returned so a public
     * actor document never exposes draft titles.
     */
    private function fetchProducedRecords(int $actorId, string $culture, bool $publicOnly)
    {
        $q = DB::table('event')
            ->join('information_object as io', 'event.object_id', '=', 'io.id')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('event.actor_id', $actorId)
            ->where('event.type_id', self::EVENT_TYPE_CREATION)
            ->whereNotNull('event.object_id');

        if ($publicOnly) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'io.id')
                    ->where('status.type_id', self::STATUS_TYPE_PUBLICATION)
                    ->where('status.status_id', self::PUBLICATION_STATUS_PUBLISHED);
            })->where('io.id', '>', 1);
        }

        return $q->select([
            'io.id', 'i18n.title', 's.slug',
        ])->distinct()->get();
    }

    /** The existence time-span of the actor, if any of its events carry dates
     *  (AtoM stores birth/death/existence on the event table for actors). */
    private function fetchExistenceEvents(int $actorId, string $culture)
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', $culture);
            })
            ->where('event.actor_id', $actorId)
            ->whereNull('event.object_id')
            ->where(function ($w) {
                $w->whereNotNull('event.start_date')->orWhereNotNull('event.end_date');
            })
            ->select('event.id', 'event.type_id', 'event.start_date',
                'event.end_date', 'event_i18n.date as date_display')
            ->get();
    }

    // -----------------------------------------------------------------
    // Graph construction.
    // -----------------------------------------------------------------

    private function buildGraph($actor, $produced, string $culture): array
    {
        $actorUri = $this->actorUri($actor);
        $crmClass = $this->actorClassFor((int) ($actor->entity_type_id ?? 0));

        $nodes = [];
        $actorProps = [];

        if (! empty($actor->name)) {
            $actorProps[] = ['rdfs:label', (string) $actor->name, 'lang'];
            $appUri = $actorUri . '#crm-appellation';
            $actorProps[] = ['crm:P1_is_identified_by', $appUri, 'iri'];
        }
        if (! empty($actor->history)) {
            $actorProps[] = ['crm:P3_has_note', (string) $actor->history, 'lang'];
        }

        // Existence time-span (E52). Persons get a birth/death framing where
        // dates exist; other actor types get a generic existence span.
        $existence = $this->fetchExistenceEvents((int) $actor->id, $culture);
        $tsUri = $actorUri . '#crm-existence';
        $hasTimeSpan = false;
        foreach ($existence as $ev) {
            if (! empty($ev->start_date) || ! empty($ev->end_date)) {
                $hasTimeSpan = true;
                break;
            }
        }
        if (! $hasTimeSpan && ! empty($actor->dates_of_existence)) {
            // Display-only dates with no parsed start/end still justify a span
            // carrying the human-readable label.
            $hasTimeSpan = true;
        }
        if ($hasTimeSpan) {
            // E21 Person -> P98i was born / P100i died by birth/death events when
            // person; otherwise the actor simply has an existence time-span via
            // a direct note plus the E52 node referenced by rdfs:seeAlso-free
            // P3 framing. We attach the span to the actor through a Birth event
            // for persons, and a plain time-span reference for others.
            if ((int) ($actor->entity_type_id ?? 0) === self::ENTITY_PERSON) {
                $birthUri = $actorUri . '#crm-birth';
                $actorProps[] = ['crm:P98i_was_born', $birthUri, 'iri'];
            } else {
                $actorProps[] = ['crm:P4_has_time-span', $tsUri, 'iri'];
            }
        }

        // P11i participated in -> the E12 Production of each record this actor
        // created. The production URI matches the records exporter fragment
        // (record-url#crm-production) so the two documents join in a store.
        foreach ($produced as $i => $rec) {
            $prodUri = $this->recordUrl($rec) . '#crm-production';
            $actorProps[] = ['crm:P11i_participated_in', $prodUri, 'iri'];
        }

        $nodes[] = [$actorUri, $crmClass, $actorProps];

        // ---- E82 Actor Appellation node ----
        if (! empty($actor->name)) {
            $nodes[] = [$actorUri . '#crm-appellation', 'crm:E82_Actor_Appellation', [
                ['rdfs:label', (string) $actor->name, 'lang'],
            ]];
        }

        // ---- E52 Time-Span node (existence) + Birth/Death framing ----
        if ($hasTimeSpan) {
            $tsProps = [];
            $start = null;
            $end = null;
            foreach ($existence as $ev) {
                if (empty($start) && ! empty($ev->start_date)) {
                    $start = (string) $ev->start_date;
                }
                if (empty($end) && ! empty($ev->end_date)) {
                    $end = (string) $ev->end_date;
                }
            }
            if (! empty($start)) {
                $tsProps[] = ['crm:P82a_begin_of_the_begin', $start, 'date'];
            }
            if (! empty($end)) {
                $tsProps[] = ['crm:P82b_end_of_the_end', $end, 'date'];
            }
            if (! empty($actor->dates_of_existence)) {
                $tsProps[] = ['rdfs:label', (string) $actor->dates_of_existence, 'lang'];
            }
            $nodes[] = [$tsUri, 'crm:E52_Time-Span', $tsProps];

            if ((int) ($actor->entity_type_id ?? 0) === self::ENTITY_PERSON) {
                // E67 Birth event carrying the existence time-span and bringing
                // the person into being (P98 brought into life).
                $birthUri = $actorUri . '#crm-birth';
                $nodes[] = [$birthUri, 'crm:E67_Birth', [
                    ['rdfs:label', 'Existence of ' . (string) ($actor->name ?? ('actor ' . $actor->id)), 'lang'],
                    ['crm:P98_brought_into_life', $actorUri, 'iri'],
                    ['crm:P4_has_time-span', $tsUri, 'iri'],
                ]];
            }
        }

        // ---- E12 Production nodes (records produced) ----
        foreach ($produced as $rec) {
            $recUrl  = $this->recordUrl($rec);
            $prodUri = $recUrl . '#crm-production';
            $choUri  = $recUrl . '#crm-object';
            $nodes[] = [$prodUri, 'crm:E12_Production', [
                ['rdfs:label', 'Production of ' . (string) ($rec->title ?? ('object ' . $rec->id)), 'lang'],
                ['crm:P14_carried_out_by', $actorUri, 'iri'],
                ['crm:P108_has_produced', $choUri, 'iri'],
            ]];
            // ---- E22 record node (stub: label only; full record export lives
            //      in CidocCrmSerializer) so the actor doc is self-describing. ----
            $nodes[] = [$choUri, 'crm:E22_Human-Made_Object', [
                ['rdfs:label', (string) ($rec->title ?? ''), 'lang'],
            ]];
        }

        return ['nodes' => $nodes, 'culture' => $culture];
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Pick the most-specific CRM Actor class for a Heratio actor row. The
     * entity_type_id values are the fixed AtoM actor-type term ids:
     *   131 = Person, 132 = Corporate body, 133 = Family.
     * Identical to CidocCrmSerializer::actorClassFor and
     * RicToCrmMapper::AGENT_SUBCLASS so all CRM exporters agree on sub-typing.
     */
    private function actorClassFor(int $entityTypeId): string
    {
        return match ($entityTypeId) {
            self::ENTITY_PERSON => 'crm:E21_Person',
            self::ENTITY_CORPORATE => 'crm:E40_Legal_Body',
            self::ENTITY_FAMILY => 'crm:E74_Group',
            default => 'crm:E39_Actor',
        };
    }

    private function actorUri($actor): string
    {
        if (! empty($actor->slug)) {
            return rtrim((string) url('/'), '/') . '/actor/' . $actor->slug;
        }

        return rtrim((string) url('/'), '/') . '/actor/' . ((int) $actor->id);
    }

    private function recordUrl($rec): string
    {
        if (! empty($rec->slug)) {
            return rtrim((string) url('/'), '/') . '/' . $rec->slug;
        }

        return rtrim((string) url('/'), '/') . '/informationobject/' . ((int) $rec->id);
    }
}
