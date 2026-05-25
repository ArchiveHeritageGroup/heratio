<?php

/**
 * PremisInMetsBuilder - emits a METS <digiprovMD> element wrapping inline
 * PREMIS event records for a given information object.
 *
 * Phase 1 of #658 (METS per-IO exporter). The builder is split out of
 * MetsSerializer so that PREMIS-in-METS emission stays focused and is
 * trivially unit-testable in isolation.
 *
 * Data source: the preservation_event table (the same one ProvOSerializer
 * uses). For each row we emit a single <premis:event> child wrapped in
 * METS administrative metadata scaffolding (digiprovMD/mdWrap/xmlData).
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use XMLWriter;

class PremisInMetsBuilder
{
    /**
     * Emit one <digiprovMD> per preservation_event row attached to the IO
     * (or to any digital_object the IO owns). When no events exist the
     * caller still receives a valid (but empty) sequence — the surrounding
     * <amdSec> will simply contain zero children.
     */
    public function appendDigiprovMd(XMLWriter $w, int $ioId): void
    {
        if (! Schema::hasTable('preservation_event')) {
            return;
        }

        $digitalObjectIds = Schema::hasTable('digital_object')
            ? DB::table('digital_object')
                ->where('object_id', $ioId)
                ->pluck('id')
                ->all()
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
            $w->startElement('digiprovMD');
            $w->writeAttribute('ID', 'digiprov-'.((int) $row->id));

            $w->startElement('mdWrap');
            $w->writeAttribute('MDTYPE', 'PREMIS:EVENT');
            $w->startElement('xmlData');

            $w->startElementNs('premis', 'event', null);

            $w->startElementNs('premis', 'eventIdentifier', null);
            $w->writeElementNs('premis', 'eventIdentifierType', null, 'local');
            $w->writeElementNs('premis', 'eventIdentifierValue', null, (string) $row->id);
            $w->endElement(); // premis:eventIdentifier

            $w->writeElementNs('premis', 'eventType', null, (string) $row->event_type);
            $w->writeElementNs('premis', 'eventDateTime', null, $this->toIso8601((string) $row->event_datetime));

            if (! empty($row->event_detail)) {
                $w->writeElementNs('premis', 'eventDetail', null, (string) $row->event_detail);
            }

            // eventOutcomeInformation
            $w->startElementNs('premis', 'eventOutcomeInformation', null);
            $w->writeElementNs('premis', 'eventOutcome', null, (string) ($row->event_outcome ?? 'unknown'));
            if (! empty($row->event_outcome_detail)) {
                $w->startElementNs('premis', 'eventOutcomeDetail', null);
                $w->writeElementNs('premis', 'eventOutcomeDetailNote', null, (string) $row->event_outcome_detail);
                $w->endElement(); // premis:eventOutcomeDetail
            }
            $w->endElement(); // premis:eventOutcomeInformation

            // linkingAgentIdentifier
            if (! empty($row->linking_agent_value)) {
                $w->startElementNs('premis', 'linkingAgentIdentifier', null);
                $w->writeElementNs('premis', 'linkingAgentIdentifierType', null, (string) ($row->linking_agent_type ?? 'system'));
                $w->writeElementNs('premis', 'linkingAgentIdentifierValue', null, (string) $row->linking_agent_value);
                $w->endElement(); // premis:linkingAgentIdentifier
            }

            $w->endElement(); // premis:event

            $w->endElement(); // xmlData
            $w->endElement(); // mdWrap
            $w->endElement(); // digiprovMD
        }
    }

    private function toIso8601(?string $dt): string
    {
        if (! $dt) {
            return '';
        }
        $t = strtotime($dt);

        return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : (string) $dt;
    }
}
