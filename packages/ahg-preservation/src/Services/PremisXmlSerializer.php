<?php

/**
 * PremisXmlSerializer - emits a full PREMIS 3.0 XML document for an IO.
 *
 * Produces the canonical four-entity document required by ISO 14721 / OAIS
 * preservation packages:
 *
 *   <premis:premis>
 *     <object xsi:type="intellectualEntity"> ... </object>     <- the IO itself
 *     <object xsi:type="representation"> ... </object>         <- one per IO
 *     <object xsi:type="file"> ... </object>                   <- one per digital_object
 *     <event> ... </event>                                     <- one per preservation_event
 *     <agent> ... </agent>                                     <- per actor + system agent
 *     <rights> ... </rights>                                   <- one per ahg_premis_rights row
 *   </premis:premis>
 *
 * Issue #653 Phase 1.
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

namespace AhgPreservation\Services;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use XMLWriter;

class PremisXmlSerializer
{
    public const PREMIS_NS  = 'http://www.loc.gov/premis/v3';
    public const XSI_NS     = 'http://www.w3.org/2001/XMLSchema-instance';
    public const SCHEMA_LOC = 'http://www.loc.gov/premis/v3 http://www.loc.gov/standards/premis/v3/premis.xsd';

    public function __construct(protected PremisRightsService $rights)
    {
    }

    /**
     * Build the PREMIS XML document for the IO.
     */
    public function serialize(int $ioId): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('premis', 'premis', self::PREMIS_NS);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::XSI_NS);
        $w->writeAttributeNs('xsi', 'schemaLocation', null, self::SCHEMA_LOC);
        $w->writeAttribute('version', '3.0');

        $this->writeIntellectualEntity($w, $ioId);
        $this->writeRepresentation($w, $ioId);
        $this->writeFiles($w, $ioId);
        $this->writeEvents($w, $ioId);
        $this->writeAgents($w, $ioId);
        $this->writeRights($w, $ioId);

        $w->endElement(); // premis:premis
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * Validate the produced XML against the bundled PREMIS 3.0 XSD.
     * Returns array of libxml errors (empty == valid).
     */
    public function validate(string $xml): array
    {
        $xsdPath = __DIR__ . '/../../resources/schemas/premis-3-0.xsd';
        if (! is_file($xsdPath)) {
            return [['message' => 'XSD not found at ' . $xsdPath, 'level' => LIBXML_ERR_FATAL]];
        }

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument();
        $loaded = $doc->loadXML($xml);
        if (! $loaded) {
            $errors = $this->collectLibxmlErrors();
            libxml_use_internal_errors($prev);
            return $errors;
        }

        $doc->schemaValidate($xsdPath);
        $errors = $this->collectLibxmlErrors();

        libxml_use_internal_errors($prev);
        return $errors;
    }

    protected function collectLibxmlErrors(): array
    {
        $out = [];
        foreach (libxml_get_errors() as $e) {
            $out[] = [
                'level'   => $e->level,
                'code'    => $e->code,
                'line'    => $e->line,
                'message' => trim($e->message),
            ];
        }
        libxml_clear_errors();
        return $out;
    }

    // ------------------------------------------------------------------
    // Entity writers
    // ------------------------------------------------------------------

    protected function writeIntellectualEntity(XMLWriter $w, int $ioId): void
    {
        $io = DB::table('information_object')->where('id', $ioId)->first();
        if (! $io) {
            return;
        }
        $title = $this->ioTitle($ioId);

        $w->startElementNs('premis', 'object', null);
        $w->writeAttributeNs('xsi', 'type', null, 'premis:intellectualEntity');

        $this->writeObjectIdentifier($w, 'heratio-io', (string) $ioId);

        if ($title !== null) {
            $w->writeElementNs('premis', 'significantProperties', null);
        }

        $w->endElement();
    }

    protected function writeRepresentation(XMLWriter $w, int $ioId): void
    {
        $w->startElementNs('premis', 'object', null);
        $w->writeAttributeNs('xsi', 'type', null, 'premis:representation');

        $this->writeObjectIdentifier($w, 'heratio-representation', sprintf('rep-%d', $ioId));

        // Link the representation to its IE parent.
        $w->startElementNs('premis', 'relationship', null);
        $w->writeElementNs('premis', 'relationshipType', null, 'structural');
        $w->writeElementNs('premis', 'relationshipSubType', null, 'represents');
        $w->startElementNs('premis', 'relatedObjectIdentifier', null);
        $w->writeElementNs('premis', 'relatedObjectIdentifierType', null, 'heratio-io');
        $w->writeElementNs('premis', 'relatedObjectIdentifierValue', null, (string) $ioId);
        $w->endElement();
        $w->endElement();

        $w->endElement();
    }

    protected function writeFiles(XMLWriter $w, int $ioId): void
    {
        if (! Schema::hasTable('digital_object')) {
            return;
        }
        $dos = DB::table('digital_object')->where('object_id', $ioId)->get();
        foreach ($dos as $do) {
            $w->startElementNs('premis', 'object', null);
            $w->writeAttributeNs('xsi', 'type', null, 'premis:file');

            $this->writeObjectIdentifier($w, 'heratio-digital-object', (string) $do->id);

            $w->startElementNs('premis', 'objectCharacteristics', null);
            $w->writeElementNs('premis', 'compositionLevel', null, '0');

            // Fixity
            $checksum = $this->latestChecksum((int) $do->id) ?? $this->checksumFromObjectRow($do);
            if ($checksum) {
                $w->startElementNs('premis', 'fixity', null);
                $w->writeElementNs('premis', 'messageDigestAlgorithm', null, $checksum['algorithm']);
                $w->writeElementNs('premis', 'messageDigest', null, $checksum['value']);
                $w->writeElementNs('premis', 'messageDigestOriginator', null, 'heratio-preservation');
                $w->endElement();
            }

            if (! empty($do->byte_size)) {
                $w->writeElementNs('premis', 'size', null, (string) $do->byte_size);
            }

            // Format
            $format = $this->objectFormat((int) $do->id);
            $w->startElementNs('premis', 'format', null);
            $w->startElementNs('premis', 'formatDesignation', null);
            $w->writeElementNs('premis', 'formatName', null, $format['name'] ?? ($do->mime_type ?: 'application/octet-stream'));
            if (! empty($format['version'])) {
                $w->writeElementNs('premis', 'formatVersion', null, (string) $format['version']);
            }
            $w->endElement();
            if (! empty($format['pronom'])) {
                $w->startElementNs('premis', 'formatRegistry', null);
                $w->writeElementNs('premis', 'formatRegistryName', null, 'PRONOM');
                $w->writeElementNs('premis', 'formatRegistryKey', null, (string) $format['pronom']);
                $w->endElement();
            }
            $w->endElement(); // format

            $w->endElement(); // objectCharacteristics

            if (! empty($do->name)) {
                $w->writeElementNs('premis', 'originalName', null, (string) $do->name);
            }

            // Link this file to its representation.
            $w->startElementNs('premis', 'relationship', null);
            $w->writeElementNs('premis', 'relationshipType', null, 'structural');
            $w->writeElementNs('premis', 'relationshipSubType', null, 'is included in');
            $w->startElementNs('premis', 'relatedObjectIdentifier', null);
            $w->writeElementNs('premis', 'relatedObjectIdentifierType', null, 'heratio-representation');
            $w->writeElementNs('premis', 'relatedObjectIdentifierValue', null, sprintf('rep-%d', $ioId));
            $w->endElement();
            $w->endElement();

            $w->endElement(); // object
        }
    }

    protected function writeEvents(XMLWriter $w, int $ioId): void
    {
        if (! Schema::hasTable('preservation_event')) {
            return;
        }
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
            $w->startElementNs('premis', 'event', null);

            $w->startElementNs('premis', 'eventIdentifier', null);
            $w->writeElementNs('premis', 'eventIdentifierType', null, 'heratio-preservation-event');
            $w->writeElementNs('premis', 'eventIdentifierValue', null, (string) $row->id);
            $w->endElement();

            $w->writeElementNs('premis', 'eventType', null, (string) $row->event_type);
            $w->writeElementNs('premis', 'eventDateTime', null, $this->toIso8601((string) $row->event_datetime));

            if (! empty($row->event_detail)) {
                $w->writeElementNs('premis', 'eventDetailInformation', null);
            }

            $w->startElementNs('premis', 'eventOutcomeInformation', null);
            $w->writeElementNs('premis', 'eventOutcome', null, (string) ($row->event_outcome ?? 'unknown'));
            if (! empty($row->event_outcome_detail)) {
                $w->startElementNs('premis', 'eventOutcomeDetail', null);
                $w->writeElementNs('premis', 'eventOutcomeDetailNote', null, (string) $row->event_outcome_detail);
                $w->endElement();
            }
            $w->endElement();

            // Linking agent
            $w->startElementNs('premis', 'linkingAgentIdentifier', null);
            $w->writeElementNs('premis', 'linkingAgentIdentifierType', null, (string) ($row->linking_agent_type ?? 'system'));
            $w->writeElementNs('premis', 'linkingAgentIdentifierValue', null, (string) ($row->linking_agent_value ?? 'heratio-preservation'));
            $w->endElement();

            // Linking object (the digital object the event acted on)
            if (! empty($row->digital_object_id)) {
                $w->startElementNs('premis', 'linkingObjectIdentifier', null);
                $w->writeElementNs('premis', 'linkingObjectIdentifierType', null, 'heratio-digital-object');
                $w->writeElementNs('premis', 'linkingObjectIdentifierValue', null, (string) $row->digital_object_id);
                $w->endElement();
            }

            $w->endElement(); // premis:event
        }
    }

    protected function writeAgents(XMLWriter $w, int $ioId): void
    {
        // Always emit the system agent.
        $w->startElementNs('premis', 'agent', null);
        $w->startElementNs('premis', 'agentIdentifier', null);
        $w->writeElementNs('premis', 'agentIdentifierType', null, 'system');
        $w->writeElementNs('premis', 'agentIdentifierValue', null, 'heratio-preservation');
        $w->endElement();
        $w->writeElementNs('premis', 'agentName', null, 'Heratio Preservation Engine');
        $w->writeElementNs('premis', 'agentType', null, 'software');
        $w->endElement();

        // Plus one agent per distinct user actor referenced by events.
        if (Schema::hasTable('preservation_event')) {
            $digitalObjectIds = Schema::hasTable('digital_object')
                ? DB::table('digital_object')->where('object_id', $ioId)->pluck('id')->all()
                : [];

            $agents = DB::table('preservation_event')
                ->where(function ($q) use ($ioId, $digitalObjectIds) {
                    $q->where('information_object_id', $ioId);
                    if (! empty($digitalObjectIds)) {
                        $q->orWhereIn('digital_object_id', $digitalObjectIds);
                    }
                })
                ->whereIn('linking_agent_type', ['user', 'organization'])
                ->whereNotNull('linking_agent_value')
                ->select('linking_agent_type', 'linking_agent_value')
                ->distinct()
                ->get();

            foreach ($agents as $a) {
                $w->startElementNs('premis', 'agent', null);
                $w->startElementNs('premis', 'agentIdentifier', null);
                $w->writeElementNs('premis', 'agentIdentifierType', null, (string) $a->linking_agent_type);
                $w->writeElementNs('premis', 'agentIdentifierValue', null, (string) $a->linking_agent_value);
                $w->endElement();
                $w->writeElementNs('premis', 'agentType', null, (string) $a->linking_agent_type);
                $w->endElement();
            }
        }
    }

    protected function writeRights(XMLWriter $w, int $ioId): void
    {
        $rows = $this->rights->getForIo($ioId);
        foreach ($rows as $r) {
            $w->startElementNs('premis', 'rights', null);
            $w->startElementNs('premis', 'rightsStatement', null);

            $w->startElementNs('premis', 'rightsStatementIdentifier', null);
            $w->writeElementNs('premis', 'rightsStatementIdentifierType', null, 'heratio-premis-rights');
            $w->writeElementNs('premis', 'rightsStatementIdentifierValue', null, (string) $r->id);
            $w->endElement();

            $w->writeElementNs('premis', 'rightsBasis', null, (string) $r->rights_basis);

            $w->startElementNs('premis', 'rightsGranted', null);
            $w->writeElementNs('premis', 'act', null, (string) $r->rights_granted_act);
            if (! empty($r->rights_granted_restriction)) {
                $w->writeElementNs('premis', 'restriction', null, (string) $r->rights_granted_restriction);
            }
            if (! empty($r->applicable_dates_start) || ! empty($r->applicable_dates_end)) {
                $w->startElementNs('premis', 'termOfGrant', null);
                if (! empty($r->applicable_dates_start)) {
                    $w->writeElementNs('premis', 'startDate', null, (string) $r->applicable_dates_start);
                }
                if (! empty($r->applicable_dates_end)) {
                    $w->writeElementNs('premis', 'endDate', null, (string) $r->applicable_dates_end);
                }
                $w->endElement();
            }
            $w->endElement(); // rightsGranted

            // Link back to the IO this statement applies to.
            $w->startElementNs('premis', 'linkingObjectIdentifier', null);
            $w->writeElementNs('premis', 'linkingObjectIdentifierType', null, 'heratio-io');
            $w->writeElementNs('premis', 'linkingObjectIdentifierValue', null, (string) $ioId);
            $w->endElement();

            $w->endElement(); // rightsStatement
            $w->endElement(); // rights
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function writeObjectIdentifier(XMLWriter $w, string $type, string $value): void
    {
        $w->startElementNs('premis', 'objectIdentifier', null);
        $w->writeElementNs('premis', 'objectIdentifierType', null, $type);
        $w->writeElementNs('premis', 'objectIdentifierValue', null, $value);
        $w->endElement();
    }

    protected function ioTitle(int $ioId): ?string
    {
        if (! Schema::hasTable('information_object_i18n')) {
            return null;
        }
        $row = DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', 'en')
            ->first();
        return $row->title ?? null;
    }

    protected function latestChecksum(int $digitalObjectId): ?array
    {
        if (! Schema::hasTable('preservation_checksum')) {
            return null;
        }
        $row = DB::table('preservation_checksum')
            ->where('digital_object_id', $digitalObjectId)
            ->orderByDesc('generated_at')
            ->first();
        if (! $row) {
            return null;
        }
        return ['algorithm' => (string) $row->algorithm, 'value' => (string) $row->checksum_value];
    }

    protected function checksumFromObjectRow(object $do): ?array
    {
        if (empty($do->checksum) || empty($do->checksum_type)) {
            return null;
        }
        return ['algorithm' => (string) $do->checksum_type, 'value' => (string) $do->checksum];
    }

    protected function objectFormat(int $digitalObjectId): array
    {
        if (! Schema::hasTable('preservation_object_format')) {
            return [];
        }
        $row = DB::table('preservation_object_format as pof')
            ->leftJoin('preservation_format as pf', 'pf.id', '=', 'pof.format_id')
            ->select('pof.*', 'pf.format_name', 'pf.puid')
            ->where('pof.digital_object_id', $digitalObjectId)
            ->first();
        if (! $row) {
            return [];
        }
        return [
            'name'    => $row->format_name ?: $row->detected_format_name ?? null,
            'version' => $row->detected_format_version ?? null,
            'pronom'  => $row->puid ?: ($row->detected_puid ?? null),
        ];
    }

    protected function toIso8601(string $datetime): string
    {
        try {
            return (new \DateTimeImmutable($datetime))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable $e) {
            return $datetime;
        }
    }
}
