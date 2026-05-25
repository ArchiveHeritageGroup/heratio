<?php

/**
 * ProvOSerializer — W3C PROV-O serialisation of preservation events for an
 * information object. Emits PROV-JSON (W3C PROV-JSON) which is the canonical
 * lightweight JSON form of the PROV-O ontology.
 *
 * Phase 3 of #658 (METS + PROV-O audit). Maps the preservation_event table
 * to PROV concepts:
 *
 *   preservation_event           → prov:Activity
 *   digital_object/information_object → prov:Entity
 *   linking_agent_value (user)   → prov:Agent
 *   event_datetime               → prov:startedAtTime / prov:endedAtTime
 *   event_type                   → custom property (premis-event-type)
 *   event_outcome                → custom property (premis-event-outcome)
 *
 * Output spec: https://www.w3.org/Submission/prov-json/
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProvOSerializer
{
    public function getFormat(): string
    {
        return 'provo';
    }

    /**
     * Emit a PROV-JSON document covering the preservation_event rows
     * attached to the given information_object (or any of its digital
     * objects).
     */
    public function serializeRecord(int $informationObjectId, string $culture = 'en'): string
    {
        if (!Schema::hasTable('preservation_event')) {
            // Empty document (still valid PROV-JSON)
            return $this->emptyDocument($informationObjectId);
        }

        // Fetch events scoped to this IO + any digital_object linked to it
        $digitalObjectIds = Schema::hasTable('digital_object')
            ? DB::table('digital_object')
                ->where('object_id', $informationObjectId)
                ->pluck('id')
                ->all()
            : [];

        $eventsQuery = DB::table('preservation_event')
            ->where(function ($q) use ($informationObjectId, $digitalObjectIds) {
                $q->where('information_object_id', $informationObjectId);
                if (!empty($digitalObjectIds)) {
                    $q->orWhereIn('digital_object_id', $digitalObjectIds);
                }
            })
            ->orderBy('event_datetime');

        $events = $eventsQuery->get();

        $entityIri = $this->entityIri($informationObjectId);
        $entities  = [$entityIri => $this->buildEntity($informationObjectId, $culture)];
        $activities = [];
        $agents     = [];
        $wasGeneratedBy = [];
        $used = [];
        $wasAssociatedWith = [];

        foreach ($events as $row) {
            $activityIri = $this->activityIri((int) $row->id);
            $activities[$activityIri] = [
                'prov:startTime' => $this->toIso8601((string) $row->event_datetime),
                'ahg:eventType'  => (string) $row->event_type,
                'ahg:eventOutcome' => (string) ($row->event_outcome ?? 'unknown'),
                'ahg:eventDetail' => (string) ($row->event_detail ?? ''),
            ];

            // Used + wasGeneratedBy link the activity to the entity
            $used['_:u' . $row->id] = [
                'prov:activity' => $activityIri,
                'prov:entity'   => $entityIri,
            ];
            $wasGeneratedBy['_:g' . $row->id] = [
                'prov:activity' => $activityIri,
                'prov:entity'   => $entityIri,
            ];

            if (!empty($row->linking_agent_value)) {
                $agentIri = $this->agentIri((string) $row->linking_agent_value, (string) ($row->linking_agent_type ?? 'system'));
                if (!isset($agents[$agentIri])) {
                    $agents[$agentIri] = [
                        'prov:label' => (string) $row->linking_agent_value,
                        'ahg:agentType' => (string) ($row->linking_agent_type ?? 'system'),
                    ];
                }
                $wasAssociatedWith['_:a' . $row->id] = [
                    'prov:activity' => $activityIri,
                    'prov:agent'    => $agentIri,
                ];
            }

            // Linked object (digital_object): record as a separate entity
            if (!empty($row->digital_object_id)) {
                $doIri = $this->digitalObjectIri((int) $row->digital_object_id);
                if (!isset($entities[$doIri])) {
                    $entities[$doIri] = [
                        'prov:type' => 'premis:File',
                        'ahg:digitalObjectId' => (int) $row->digital_object_id,
                    ];
                }
            }
        }

        $prefix = [
            'prov'   => 'http://www.w3.org/ns/prov#',
            'premis' => 'http://www.loc.gov/premis/rdf/v3/',
            'xsd'    => 'http://www.w3.org/2001/XMLSchema#',
            'ahg'    => 'https://heratio.theahg.co.za/ns/ahg/',
        ];

        $doc = ['prefix' => $prefix];
        if (!empty($entities))   $doc['entity']   = $entities;
        if (!empty($activities)) $doc['activity'] = $activities;
        if (!empty($agents))     $doc['agent']    = $agents;
        if (!empty($used))       $doc['used']     = $used;
        if (!empty($wasGeneratedBy)) $doc['wasGeneratedBy'] = $wasGeneratedBy;
        if (!empty($wasAssociatedWith)) $doc['wasAssociatedWith'] = $wasAssociatedWith;

        return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildEntity(int $informationObjectId, string $culture): array
    {
        $row = DB::table('information_object as i')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->where('i.id', $informationObjectId)
            ->select('i.id', 'i.identifier', 'i18n.title')
            ->first();

        return [
            'prov:label' => (string) ($row->title ?? "Information object {$informationObjectId}"),
            'prov:type'  => 'premis:IntellectualEntity',
            'ahg:informationObjectId' => $informationObjectId,
            'ahg:identifier' => (string) ($row->identifier ?? ''),
        ];
    }

    private function emptyDocument(int $informationObjectId): string
    {
        return json_encode([
            'prefix' => [
                'prov' => 'http://www.w3.org/ns/prov#',
                'ahg'  => 'https://heratio.theahg.co.za/ns/ahg/',
            ],
            'entity' => [
                $this->entityIri($informationObjectId) => [
                    'prov:label' => "Information object {$informationObjectId}",
                    'ahg:note'   => 'No preservation events on record.',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function entityIri(int $informationObjectId): string
    {
        return "ahg:io/{$informationObjectId}";
    }

    private function activityIri(int $eventId): string
    {
        return "ahg:event/{$eventId}";
    }

    private function digitalObjectIri(int $digitalObjectId): string
    {
        return "ahg:digital_object/{$digitalObjectId}";
    }

    private function agentIri(string $value, string $type): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]/', '-', $value);
        return "ahg:agent/{$type}/{$slug}";
    }

    private function toIso8601(?string $dt): string
    {
        if (!$dt) return '';
        $t = strtotime($dt);
        return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : (string) $dt;
    }
}
