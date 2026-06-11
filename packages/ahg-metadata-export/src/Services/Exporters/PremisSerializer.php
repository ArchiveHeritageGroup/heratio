<?php

/**
 * PremisSerializer - PREMIS 3.0 preservation-metadata serializer for a single
 * information_object and the digital_object file(s) it owns.
 *
 * PREMIS (PREservation Metadata: Implementation Strategies) is the Library of
 * Congress data dictionary for the metadata an archive needs to preserve
 * digital material over time. This serializer emits a standalone
 * <premis:premis> document (namespace http://www.loc.gov/premis/v3,
 * version 3.0) carrying:
 *
 *   premis:object  - one per digital_object row: an objectIdentifier, the
 *                    objectCharacteristics (fixity = messageDigestAlgorithm +
 *                    messageDigest from digital_object.checksum/checksum_type,
 *                    size from byte_size, format from PRONOM PUID / format name
 *                    when a preservation_object_format row is present, else the
 *                    stored mime_type) and originalName from the stored file
 *                    name.
 *   premis:event   - one per preservation_event row attached to the IO or any
 *                    of its digital objects: eventType, eventDateTime,
 *                    eventOutcome, plus the linking agent / object identifiers.
 *   premis:agent   - one per distinct responsible agent / system named on the
 *                    events.
 *
 * The whole pipeline is READ-ONLY: every query is a SELECT and no row is ever
 * written or altered. Tables that do not exist (a fresh install with no
 * preservation pipeline yet) are probed with Schema::hasTable() and simply
 * omitted - the serializer emits whatever evidence is present and skips the
 * rest, always producing a well-formed document.
 *
 * This advances issue #1197 (unified G/L/A/M metadata surface) and the digital
 * preservation epics #1244 / #1243 by giving every record a portable,
 * standards-compliant PREMIS view of its preservation state. Mirrors the
 * structure and exposure of MetsSerializer + CidocCrmSerializer.
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
use XMLWriter;

class PremisSerializer
{
    use InformationObjectFetcher;

    /** PREMIS 3.0 namespace. */
    public const NS_PREMIS = 'http://www.loc.gov/premis/v3';

    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** PRONOM authority base for format registry keys (PUIDs). */
    public const PRONOM_BASE = 'https://www.nationalarchives.gov.uk/PRONOM/';

    /** Publication-status gate (status table; AtoM term ids). */
    private const STATUS_TYPE_PUBLICATION = 158;

    private const PUBLICATION_STATUS_PUBLISHED = 160;

    public function getFormat(): string
    {
        return 'premis';
    }

    /**
     * Serialise one information_object plus its digital_object file(s) as a
     * PREMIS 3.0 XML document.
     *
     * @param  int  $objectId  information_object.id
     * @param  string  $culture  i18n culture for labels (default 'en')
     * @param  bool  $publicOnly  when true, returns '' unless the record passes
     *                            the published-records gate (used by any public
     *                            exposure). Admin callers pass false.
     *
     * Returns '' when the IO is missing (or fails the gate) so the caller
     * decides whether that means "skip" or "404".
     */
    public function serializeRecord(int $objectId, string $culture = 'en', bool $publicOnly = false): string
    {
        if ($publicOnly && ! $this->isPublic($objectId)) {
            return '';
        }

        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $digitalObjects = $this->fetchDigitalObjects($objectId);
        $digitalObjectIds = array_map(static fn ($d) => (int) $d->id, $digitalObjects);

        $formats = $this->fetchObjectFormats($digitalObjectIds);
        $events = $this->fetchPreservationEvents($objectId, $digitalObjectIds);
        $agents = $this->collectAgents($events);

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('premis', 'premis', self::NS_PREMIS);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::NS_XSI);
        $w->writeAttributeNs(
            'xsi',
            'schemaLocation',
            null,
            self::NS_PREMIS.' '.self::NS_PREMIS.'/premis.xsd'
        );
        $w->writeAttribute('version', '3.0');

        // premis:object - one per digital_object. When the record has no
        // digital objects at all (description-only record) we still emit a
        // representation-level object so consumers get a stable anchor.
        if (! empty($digitalObjects)) {
            foreach ($digitalObjects as $do) {
                $this->writeFileObject($w, $io, $do, $formats[(int) $do->id] ?? null);
            }
        } else {
            $this->writeRepresentationObject($w, $io);
        }

        // premis:event - any recorded preservation / ingest / fixity event.
        foreach ($events as $event) {
            $this->writeEvent($w, $event);
        }

        // premis:agent - the responsible agents / systems named on the events.
        foreach ($agents as $agent) {
            $this->writeAgent($w, $agent);
        }

        $w->endElement(); // premis:premis
        $w->endDocument();

        return $w->outputMemory();
    }

    // -----------------------------------------------------------------
    // premis:object writers
    // -----------------------------------------------------------------

    /**
     * A file-level premis:object for one digital_object row.
     */
    private function writeFileObject(XMLWriter $w, $io, $do, ?object $format): void
    {
        $doId = (int) $do->id;

        $w->startElementNs('premis', 'object', null);
        $w->writeAttributeNs('xsi', 'type', null, 'premis:file');

        // objectIdentifier
        $this->writeObjectIdentifier($w, 'heratio-digital-object', (string) $doId);

        // objectCharacteristics: fixity + size + format
        $w->startElementNs('premis', 'objectCharacteristics', null);

        $this->writeFixity($w, $do);

        if (! empty($do->byte_size)) {
            $w->writeElementNs('premis', 'size', null, (string) ((int) $do->byte_size));
        }

        $this->writeFormat($w, $do, $format);

        $w->endElement(); // premis:objectCharacteristics

        // originalName - the stored file name.
        $originalName = trim((string) ($do->name ?? ''));
        if ($originalName !== '') {
            $w->writeElementNs('premis', 'originalName', null, $originalName);
        }

        // Tie the file back to its intellectual description for context.
        if (! empty($io->id)) {
            $w->startElementNs('premis', 'relationship', null);
            $w->writeElementNs('premis', 'relationshipType', null, 'structural');
            $w->writeElementNs('premis', 'relationshipSubType', null, 'is part of');
            $w->startElementNs('premis', 'relatedObjectIdentifier', null);
            $w->writeElementNs('premis', 'relatedObjectIdentifierType', null, 'heratio-information-object');
            $w->writeElementNs('premis', 'relatedObjectIdentifierValue', null, (string) ((int) $io->id));
            $w->endElement(); // premis:relatedObjectIdentifier
            $w->endElement(); // premis:relationship
        }

        $w->endElement(); // premis:object
    }

    /**
     * A representation-level premis:object used when a record has no
     * digital_object rows. Keeps the document well-formed and anchors any
     * description-level events.
     */
    private function writeRepresentationObject(XMLWriter $w, $io): void
    {
        $w->startElementNs('premis', 'object', null);
        $w->writeAttributeNs('xsi', 'type', null, 'premis:representation');

        $this->writeObjectIdentifier($w, 'heratio-information-object', (string) ((int) $io->id));

        $originalName = trim((string) ($io->identifier ?? $io->title ?? ''));
        if ($originalName !== '') {
            $w->writeElementNs('premis', 'originalName', null, $originalName);
        }

        $w->endElement(); // premis:object
    }

    private function writeObjectIdentifier(XMLWriter $w, string $type, string $value): void
    {
        $w->startElementNs('premis', 'objectIdentifier', null);
        $w->writeElementNs('premis', 'objectIdentifierType', null, $type);
        $w->writeElementNs('premis', 'objectIdentifierValue', null, $value);
        $w->endElement(); // premis:objectIdentifier
    }

    /**
     * premis:fixity from digital_object.checksum + checksum_type. Omitted when
     * no checksum is recorded.
     */
    private function writeFixity(XMLWriter $w, $do): void
    {
        $checksum = trim((string) ($do->checksum ?? ''));
        if ($checksum === '') {
            return;
        }

        $algorithm = $this->normaliseDigestAlgorithm((string) ($do->checksum_type ?? ''));

        $w->startElementNs('premis', 'fixity', null);
        $w->writeElementNs('premis', 'messageDigestAlgorithm', null, $algorithm);
        $w->writeElementNs('premis', 'messageDigest', null, $checksum);
        $w->writeElementNs('premis', 'messageDigestOriginator', null, (string) config('app.name', 'Heratio'));
        $w->endElement(); // premis:fixity
    }

    /**
     * premis:format. Prefers a preservation_object_format row (PRONOM PUID +
     * format name/version from a real identification tool). Falls back to the
     * digital_object.mime_type when no identification has been run.
     */
    private function writeFormat(XMLWriter $w, $do, ?object $format): void
    {
        $w->startElementNs('premis', 'format', null);

        $formatName = $format ? trim((string) ($format->format_name ?? '')) : '';
        $formatVersion = $format ? trim((string) ($format->format_version ?? '')) : '';
        $puid = $format ? trim((string) ($format->puid ?? '')) : '';
        $mime = trim((string) ($do->mime_type ?? ''));
        if ($mime === '' && $format) {
            $mime = trim((string) ($format->mime_type ?? ''));
        }

        if ($formatName !== '' || $mime !== '') {
            $w->startElementNs('premis', 'formatDesignation', null);
            // formatName: prefer the human format label, fall back to the MIME.
            $w->writeElementNs('premis', 'formatName', null, $formatName !== '' ? $formatName : $mime);
            if ($formatVersion !== '') {
                $w->writeElementNs('premis', 'formatVersion', null, $formatVersion);
            }
            $w->endElement(); // premis:formatDesignation
        }

        // formatRegistry: a PRONOM PUID when one was identified.
        if ($puid !== '') {
            $w->startElementNs('premis', 'formatRegistry', null);
            $w->writeElementNs('premis', 'formatRegistryName', null, 'PRONOM');
            $w->writeElementNs('premis', 'formatRegistryKey', null, $puid);
            $w->writeElementNs('premis', 'formatRegistryRole', null, 'specification');
            $w->endElement(); // premis:formatRegistry
        }

        // formatNote: record the identification tool when present, for provenance.
        if ($format && ! empty($format->identification_tool)) {
            $w->writeElementNs('premis', 'formatNote', null, 'Identified by '.trim((string) $format->identification_tool));
        }

        $w->endElement(); // premis:format
    }

    // -----------------------------------------------------------------
    // premis:event writer
    // -----------------------------------------------------------------

    private function writeEvent(XMLWriter $w, $row): void
    {
        $w->startElementNs('premis', 'event', null);

        $w->startElementNs('premis', 'eventIdentifier', null);
        $w->writeElementNs('premis', 'eventIdentifierType', null, 'local');
        $w->writeElementNs('premis', 'eventIdentifierValue', null, (string) ((int) $row->id));
        $w->endElement(); // premis:eventIdentifier

        $w->writeElementNs('premis', 'eventType', null, (string) ($row->event_type ?? 'unknown'));
        $w->writeElementNs('premis', 'eventDateTime', null, $this->toIso8601((string) ($row->event_datetime ?? '')));

        if (! empty($row->event_detail)) {
            $w->startElementNs('premis', 'eventDetailInformation', null);
            $w->writeElementNs('premis', 'eventDetail', null, (string) $row->event_detail);
            $w->endElement(); // premis:eventDetailInformation
        }

        $w->startElementNs('premis', 'eventOutcomeInformation', null);
        $w->writeElementNs('premis', 'eventOutcome', null, (string) ($row->event_outcome ?? 'unknown'));
        if (! empty($row->event_outcome_detail)) {
            $w->startElementNs('premis', 'eventOutcomeDetail', null);
            $w->writeElementNs('premis', 'eventOutcomeDetailNote', null, (string) $row->event_outcome_detail);
            $w->endElement(); // premis:eventOutcomeDetail
        }
        $w->endElement(); // premis:eventOutcomeInformation

        // linkingAgentIdentifier - the agent / system responsible.
        if (! empty($row->linking_agent_value)) {
            $w->startElementNs('premis', 'linkingAgentIdentifier', null);
            $w->writeElementNs('premis', 'linkingAgentIdentifierType', null, (string) ($row->linking_agent_type ?: 'system'));
            $w->writeElementNs('premis', 'linkingAgentIdentifierValue', null, (string) $row->linking_agent_value);
            $w->endElement(); // premis:linkingAgentIdentifier
        }

        // linkingObjectIdentifier - the object the event acted on.
        if (! empty($row->digital_object_id)) {
            $w->startElementNs('premis', 'linkingObjectIdentifier', null);
            $w->writeElementNs('premis', 'linkingObjectIdentifierType', null, 'heratio-digital-object');
            $w->writeElementNs('premis', 'linkingObjectIdentifierValue', null, (string) ((int) $row->digital_object_id));
            $w->endElement(); // premis:linkingObjectIdentifier
        } elseif (! empty($row->information_object_id)) {
            $w->startElementNs('premis', 'linkingObjectIdentifier', null);
            $w->writeElementNs('premis', 'linkingObjectIdentifierType', null, 'heratio-information-object');
            $w->writeElementNs('premis', 'linkingObjectIdentifierValue', null, (string) ((int) $row->information_object_id));
            $w->endElement(); // premis:linkingObjectIdentifier
        }

        $w->endElement(); // premis:event
    }

    // -----------------------------------------------------------------
    // premis:agent writer
    // -----------------------------------------------------------------

    private function writeAgent(XMLWriter $w, array $agent): void
    {
        $w->startElementNs('premis', 'agent', null);

        $w->startElementNs('premis', 'agentIdentifier', null);
        $w->writeElementNs('premis', 'agentIdentifierType', null, (string) $agent['type']);
        $w->writeElementNs('premis', 'agentIdentifierValue', null, (string) $agent['value']);
        $w->endElement(); // premis:agentIdentifier

        $w->writeElementNs('premis', 'agentName', null, (string) $agent['value']);
        $w->writeElementNs('premis', 'agentType', null, $this->mapAgentType((string) $agent['type']));

        $w->endElement(); // premis:agent
    }

    // -----------------------------------------------------------------
    // Read-only fetch helpers - all SELECT, all schema-probed.
    // -----------------------------------------------------------------

    /**
     * The digital_object rows owned by this information_object.
     */
    private function fetchDigitalObjects(int $ioId): array
    {
        if (! Schema::hasTable('digital_object')) {
            return [];
        }

        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->orderBy('id')
            ->select('id', 'name', 'path', 'mime_type', 'byte_size', 'checksum', 'checksum_type')
            ->get()
            ->all();
    }

    /**
     * The newest preservation_object_format row per digital_object (PRONOM
     * PUID + format name/version), keyed by digital_object_id. Empty when the
     * table is absent or no identification has been run.
     *
     * @param  int[]  $digitalObjectIds
     * @return array<int, object>
     */
    private function fetchObjectFormats(array $digitalObjectIds): array
    {
        if (empty($digitalObjectIds) || ! Schema::hasTable('preservation_object_format')) {
            return [];
        }

        $rows = DB::table('preservation_object_format')
            ->whereIn('digital_object_id', $digitalObjectIds)
            ->orderBy('digital_object_id')
            ->orderBy('id')
            ->select('digital_object_id', 'puid', 'mime_type', 'format_name', 'format_version', 'identification_tool')
            ->get();

        // Keep the last (newest by id) identification per digital object.
        $byDo = [];
        foreach ($rows as $r) {
            $byDo[(int) $r->digital_object_id] = $r;
        }

        return $byDo;
    }

    /**
     * The preservation_event rows attached to the IO or any of its digital
     * objects. Empty when the table is absent.
     *
     * @param  int[]  $digitalObjectIds
     */
    private function fetchPreservationEvents(int $ioId, array $digitalObjectIds): array
    {
        if (! Schema::hasTable('preservation_event')) {
            return [];
        }

        return DB::table('preservation_event')
            ->where(function ($q) use ($ioId, $digitalObjectIds) {
                $q->where('information_object_id', $ioId);
                if (! empty($digitalObjectIds)) {
                    $q->orWhereIn('digital_object_id', $digitalObjectIds);
                }
            })
            ->orderBy('event_datetime')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Distinct responsible agents from the event list, preserving first-seen
     * order. Keyed "type|value" so the same system named twice yields one
     * premis:agent.
     *
     * @param  iterable  $events
     * @return array<int, array{type:string,value:string}>
     */
    private function collectAgents(iterable $events): array
    {
        $seen = [];
        $agents = [];
        foreach ($events as $e) {
            $value = trim((string) ($e->linking_agent_value ?? ''));
            if ($value === '') {
                continue;
            }
            $type = trim((string) ($e->linking_agent_type ?? '')) ?: 'system';
            $key = $type.'|'.$value;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $agents[] = ['type' => $type, 'value' => $value];
        }

        return $agents;
    }

    /**
     * The published-records gate. True when a published status row exists for
     * this object and the object is not the synthetic root (id = 1). Mirrors
     * CidocCrmSerializer::isPublic().
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
    // Small mappers / normalisers
    // -----------------------------------------------------------------

    /**
     * Normalise a stored checksum-type string to a PREMIS-friendly digest
     * algorithm label (the controlled vocabulary the LoC fixity terms use).
     */
    private function normaliseDigestAlgorithm(string $raw): string
    {
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $raw) ?? '');

        return match ($key) {
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha384' => 'SHA-384',
            'sha512' => 'SHA-512',
            'md5' => 'MD5',
            'crc32' => 'CRC32',
            '' => 'SHA-256', // sensible default for an unlabelled digest
            default => strtoupper($raw),
        };
    }

    /**
     * Map a linking-agent type to a PREMIS agentType value.
     */
    private function mapAgentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'system', 'software', 'tool' => 'software',
            'person', 'user' => 'person',
            'organization', 'organisation', 'repository' => 'organization',
            default => 'software',
        };
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
