<?php

/**
 * ProvOGraphBuilder - materialise the PROV-O graph for a single
 * information_object as a flat list of [subject, predicate, object]
 * triples that the in-memory SPARQL engine can pattern-match against.
 *
 * The triple shape mirrors the JSON-LD emitted by ProvOSerializer so a
 * SPARQL SELECT over the graph produces results consistent with the
 * file-download artefact.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Services\Sparql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProvOGraphBuilder
{
    public const NS_PROV = 'http://www.w3.org/ns/prov#';

    public const NS_PREMIS_RDF = 'http://www.loc.gov/premis/rdf/v3/';

    public const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    public const NS_AHG = 'https://heratio.theahg.co.za/ns/ahg/';

    public const PROV_ACTIVITY = self::NS_PROV.'Activity';

    public const PROV_ENTITY = self::NS_PROV.'Entity';

    public const PROV_AGENT = self::NS_PROV.'Agent';

    /**
     * Build the triple list for the given IO. Returns an array of
     * 3-tuples: [subjectIri, predicateIri, [type=>uri|literal, value=>string]].
     *
     * @return array<int, array{0:string,1:string,2:array{type:string,value:string}}>
     */
    public function buildTriples(int $ioId): array
    {
        $triples = [];
        if (! Schema::hasTable('preservation_event')) {
            return $triples;
        }

        $entityIri = $this->entityIri($ioId);

        // The IO itself - rdf:type prov:Entity
        $triples[] = [$entityIri, self::NS_RDF.'type', $this->uri(self::PROV_ENTITY)];

        $digitalObjectIds = Schema::hasTable('digital_object')
            ? DB::table('digital_object')->where('object_id', $ioId)->pluck('id')->all()
            : [];

        $events = DB::table('preservation_event')
            ->where(function ($q) use ($ioId, $digitalObjectIds) {
                $q->where('information_object_id', $ioId);
                if (! empty($digitalObjectIds)) {
                    $q->orWhereIn('digital_object_id', $digitalObjectIds);
                }
            })
            ->orderBy('event_datetime')
            ->get();

        foreach ($events as $row) {
            $activityIri = $this->activityIri((int) $row->id);

            $triples[] = [$activityIri, self::NS_RDF.'type', $this->uri(self::PROV_ACTIVITY)];
            if (! empty($row->event_datetime)) {
                $triples[] = [$activityIri, self::NS_PROV.'startedAtTime', $this->literal($this->toIso8601((string) $row->event_datetime))];
            }
            if (! empty($row->event_type)) {
                $triples[] = [$activityIri, self::NS_AHG.'eventType', $this->literal((string) $row->event_type)];
            }
            if (isset($row->event_outcome) && $row->event_outcome !== '') {
                $triples[] = [$activityIri, self::NS_AHG.'eventOutcome', $this->literal((string) $row->event_outcome)];
            }
            if (! empty($row->event_detail)) {
                $triples[] = [$activityIri, self::NS_AHG.'eventDetail', $this->literal((string) $row->event_detail)];
            }

            // Activity / entity relationships
            $triples[] = [$activityIri, self::NS_PROV.'used', $this->uri($entityIri)];
            $triples[] = [$entityIri, self::NS_PROV.'wasGeneratedBy', $this->uri($activityIri)];

            // Agent
            if (! empty($row->linking_agent_value)) {
                $agentIri = $this->agentIri((string) $row->linking_agent_value, (string) ($row->linking_agent_type ?? 'system'));
                $triples[] = [$agentIri, self::NS_RDF.'type', $this->uri(self::PROV_AGENT)];
                $triples[] = [$agentIri, self::NS_AHG.'agentLabel', $this->literal((string) $row->linking_agent_value)];
                $triples[] = [$activityIri, self::NS_PROV.'wasAssociatedWith', $this->uri($agentIri)];
            }

            // Linked digital object (extra prov:Entity)
            if (! empty($row->digital_object_id)) {
                $doIri = $this->digitalObjectIri((int) $row->digital_object_id);
                $triples[] = [$doIri, self::NS_RDF.'type', $this->uri(self::PROV_ENTITY)];
                $triples[] = [$doIri, self::NS_AHG.'digitalObjectId', $this->literal((string) (int) $row->digital_object_id)];
            }
        }

        return $triples;
    }

    public function entityIri(int $ioId): string
    {
        return self::NS_AHG.'io/'.$ioId;
    }

    public function activityIri(int $eventId): string
    {
        return self::NS_AHG.'event/'.$eventId;
    }

    public function digitalObjectIri(int $doId): string
    {
        return self::NS_AHG.'digital_object/'.$doId;
    }

    public function agentIri(string $value, string $type): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]/', '-', $value);
        return self::NS_AHG.'agent/'.$type.'/'.$slug;
    }

    private function toIso8601(?string $dt): string
    {
        if (! $dt) {
            return '';
        }
        $t = strtotime($dt);
        return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : (string) $dt;
    }

    private function uri(string $iri): array
    {
        return ['type' => 'uri', 'value' => $iri];
    }

    private function literal(string $value): array
    {
        return ['type' => 'literal', 'value' => $value];
    }
}
