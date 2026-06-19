<?php

/**
 * MarcXmlImporter - parse a MARCXML document and create / update
 * information_object rows (Heratio archival descriptions).
 *
 * Two-phase API:
 *
 *   $importer = new MarcXmlImporter();
 *   $preview  = $importer->preview($xmlString, $culture);  // dry-run, no writes
 *   $result   = $importer->commit($xmlString, $culture);   // writes + audit
 *
 * Preview returns an array of parsed records each with:
 *   ['control_number' => '...', 'title' => '...', 'will_create' => bool,
 *    'matched_io_id' => ?int, 'fields' => [...], 'warnings' => [...]]
 *
 * Commit returns the same shape augmented with 'io_id' => int and an audit
 * row id ('audit_id' => int) per record. Hash-chained via
 * AhgAuditTrail\Services\AuditLogger so import events join the same
 * tamper-evidence chain as edit events.
 *
 * MARCXML field -> Heratio column crosswalk mirrors the export side
 * (see MarcxmlSerializer for the inverse mapping). Round-trip safe for
 * the publicly populated fields: title, identifier, scope_and_content,
 * extent_and_medium, archival_history, acquisition,
 * reproduction_conditions, access_conditions, related_units_of_description.
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

namespace AhgMetadataExport\Services\Importers;

use AhgMetadataExport\Services\Rda\RdaCarrierMapper;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarcXmlImporter
{
    public const NS_MARC = 'http://www.loc.gov/MARC21/slim';

    /** @var string|null culture used for the i18n side of writes */
    private string $culture = 'en';

    private RdaCarrierMapper $rda;

    /** @var class-string|null lazy reference to the audit logger if installed */
    private ?string $auditLoggerClass = null;

    public function __construct(?RdaCarrierMapper $rda = null)
    {
        $this->rda = $rda ?: new RdaCarrierMapper();
        if (class_exists('\\AhgAuditTrail\\Services\\AuditLogger')) {
            $this->auditLoggerClass = '\\AhgAuditTrail\\Services\\AuditLogger';
        }
    }

    /**
     * Validate a MARCXML payload against the vendored LoC schema.
     * Returns [valid, errors]. Errors are libxml-formatted strings.
     *
     * @return array{0: bool, 1: array<int,string>}
     */
    public function validate(string $xml): array
    {
        $schemaPath = __DIR__.'/../../../resources/schemas/MARC21slim.xsd';
        if (! is_readable($schemaPath)) {
            return [false, ['Vendored MARC21slim.xsd schema not found at '.$schemaPath]];
        }
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml)) {
            $errs = $this->collectLibxmlErrors();
            libxml_use_internal_errors($prev);
            return [false, $errs ?: ['Not well-formed XML']];
        }

        // Wrap a bare <record> in <collection> for schema validation
        if ($dom->documentElement && $dom->documentElement->localName === 'record') {
            $wrapped = new DOMDocument();
            $wrapped->loadXML('<collection xmlns="'.self::NS_MARC.'"/>');
            $imported = $wrapped->importNode($dom->documentElement, true);
            $wrapped->documentElement->appendChild($imported);
            $dom = $wrapped;
        }

        $ok = @$dom->schemaValidate($schemaPath);
        $errors = $ok ? [] : $this->collectLibxmlErrors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return [(bool) $ok, $errors];
    }

    /**
     * Parse the MARCXML payload and return a preview of every record that
     * would be created/updated. No DB writes.
     *
     * @return array<int, array<string,mixed>>
     */
    public function preview(string $xml, string $culture = 'en'): array
    {
        $this->culture = $culture;
        $records = $this->parseRecords($xml);
        $out = [];
        foreach ($records as $rec) {
            $out[] = $this->describeRecord($rec, withMatch: true);
        }
        return $out;
    }

    /**
     * Parse + write every record. Each successful write creates a
     * chained audit row. Returns the same shape as preview() plus
     * 'io_id' and 'audit_id'.
     *
     * @return array<int, array<string,mixed>>
     */
    public function commit(string $xml, string $culture = 'en'): array
    {
        $this->culture = $culture;
        $records = $this->parseRecords($xml);
        $out = [];

        foreach ($records as $rec) {
            $desc = $this->describeRecord($rec, withMatch: true);
            if (empty($desc['title'])) {
                $desc['warnings'][] = 'Skipped: no 245$a title';
                $desc['io_id'] = null;
                $desc['audit_id'] = null;
                $out[] = $desc;
                continue;
            }
            try {
                [$ioId, $action] = $this->persist($desc);
                $desc['io_id'] = $ioId;
                $desc['action'] = $action;
                $desc['authority_links_created'] = $this->persistAuthorityLinks(
                    $ioId,
                    $desc['authority_links'] ?? []
                );
                $desc['audit_id'] = $this->logAudit($ioId, $action, $desc);
            } catch (Throwable $e) {
                $desc['error'] = $e->getMessage();
                $desc['io_id'] = null;
                $desc['audit_id'] = null;
                // Keep the result shape stable on the error path: callers (and the
                // MARC round-trip test) always expect the authority_links_created counter.
                $desc['authority_links_created'] = 0;
            }
            $out[] = $desc;
        }

        return $out;
    }

    /**
     * Low-level: extract all <record> nodes from a MARCXML document.
     *
     * @return array<int, DOMElement>
     */
    public function parseRecords(string $xml): array
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml)) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('marc', self::NS_MARC);

        // Accept both namespaced and bare records (some clients drop xmlns).
        $nodes = $xpath->query('//marc:record');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//record');
        }
        $out = [];
        foreach ($nodes ?: [] as $n) {
            if ($n instanceof DOMElement) {
                $out[] = $n;
            }
        }
        return $out;
    }

    /**
     * Translate one <record> element into a Heratio-shaped associative
     * array of column values.
     *
     * @return array<string, mixed>
     */
    public function describeRecord(DOMElement $record, bool $withMatch = false): array
    {
        $controlfields = [];
        $datafields = [];
        foreach ($record->childNodes as $child) {
            if (! ($child instanceof DOMElement)) {
                continue;
            }
            if ($child->localName === 'controlfield') {
                $controlfields[$child->getAttribute('tag')] = trim($child->textContent);
            } elseif ($child->localName === 'datafield') {
                $tag = $child->getAttribute('tag');
                $subfields = [];
                foreach ($child->childNodes as $sf) {
                    if ($sf instanceof DOMElement && $sf->localName === 'subfield') {
                        $code = $sf->getAttribute('code');
                        // datafields can repeat subfield codes; preserve list
                        $subfields[$code][] = trim($sf->textContent);
                    }
                }
                $datafields[] = [
                    'tag' => $tag,
                    'ind1' => $child->getAttribute('ind1'),
                    'ind2' => $child->getAttribute('ind2'),
                    'subfields' => $subfields,
                ];
            }
        }

        $sub = function (string $tag, string $code) use ($datafields): ?string {
            foreach ($datafields as $f) {
                if ($f['tag'] === $tag && isset($f['subfields'][$code][0])) {
                    return $f['subfields'][$code][0];
                }
            }
            return null;
        };
        $subAll = function (string $tag, string $code) use ($datafields): array {
            $out = [];
            foreach ($datafields as $f) {
                if ($f['tag'] === $tag && isset($f['subfields'][$code])) {
                    foreach ($f['subfields'][$code] as $v) {
                        $out[] = $v;
                    }
                }
            }
            return $out;
        };

        $title = $sub('245', 'a');
        $identifier = $controlfields['001'] ?? null;
        $scope = $sub('520', 'a');
        $extent = $sub('300', 'a');
        $archivalHistory = $sub('561', 'a');
        $acquisition = $sub('541', 'a');
        $reproConditions = $sub('540', 'a');
        $accessConditions = $sub('506', 'a');
        $relatedUnits = $sub('544', 'a');

        // RDA round-trip awareness: parse 338$a and remember it so the
        // commit path can flag a mismatch if the existing IO's
        // digital_object MIME maps to a different carrier.
        $carrierTerm = null;
        foreach ($datafields as $f) {
            if ($f['tag'] === '338' && isset($f['subfields']['a'][0])) {
                $carrierTerm = $f['subfields']['a'][0];
                break;
            }
        }

        $warnings = [];
        if (empty($title)) {
            $warnings[] = 'No 245$a title - record will be skipped on commit';
        }
        if (! isset($controlfields['001'])) {
            $warnings[] = 'No 001 control number - cannot round-trip to existing IO';
        }

        $matchedIoId = null;
        $willCreate = true;
        if ($withMatch && $identifier !== null && $identifier !== '') {
            $matchedIoId = $this->matchExisting($identifier);
            $willCreate = $matchedIoId === null;
        }

        return [
            'control_number' => $identifier,
            'title' => $title,
            'scope_and_content' => $scope,
            'extent_and_medium' => $extent,
            'archival_history' => $archivalHistory,
            'acquisition' => $acquisition,
            'reproduction_conditions' => $reproConditions,
            'access_conditions' => $accessConditions,
            'related_units_of_description' => $relatedUnits,
            'subjects' => $subAll('650', 'a'),
            'places' => $subAll('651', 'a'),
            'genres' => $subAll('655', 'a'),
            'creators' => $subAll('100', 'a') + $subAll('110', 'a') + $subAll('111', 'a'),
            // Authority links parsed from 6XX $0. Each entry pairs the heading
            // text ($a) with its authority URI ($0) and the source MARC tag so
            // the commit path can round-trip them into library_subject_authority
            // + library_item_authority_link.
            'authority_links' => $this->extractAuthorityLinks($datafields),
            'carrier_term' => $carrierTerm,
            'matched_io_id' => $matchedIoId,
            'will_create' => $willCreate,
            'warnings' => $warnings,
        ];
    }

    /**
     * Pull 6XX $a/$0 pairs out of the parsed datafields. Only 650/651/655 are
     * authority-controlled subject access points in Heratio's crosswalk.
     *
     * @param array<int, array<string, mixed>> $datafields
     * @return array<int, array{tag:string,heading:string,uri:?string,subject_type:string}>
     */
    private function extractAuthorityLinks(array $datafields): array
    {
        $typeByTag = ['650' => 'topic', '651' => 'place', '655' => 'genre'];
        $out = [];
        foreach ($datafields as $f) {
            $tag = $f['tag'] ?? '';
            if (! isset($typeByTag[$tag])) {
                continue;
            }
            $headings = $f['subfields']['a'] ?? [];
            $uris = $f['subfields']['0'] ?? [];
            foreach ($headings as $i => $heading) {
                $heading = trim((string) $heading);
                if ($heading === '') {
                    continue;
                }
                $out[] = [
                    'tag'          => $tag,
                    'heading'      => $heading,
                    'uri'          => isset($uris[$i]) ? trim((string) $uris[$i]) : null,
                    'subject_type' => $typeByTag[$tag],
                ];
            }
        }

        return $out;
    }

    /**
     * Round-trip authority links: for every parsed 6XX $0, find-or-create a
     * library_subject_authority record (matched by uri, else heading) and link
     * it to the library_item that wraps this IO via library_item_authority_link.
     * No-ops gracefully when the library tables are absent or the IO has no
     * library_item wrapper (archival-only records).
     *
     * @param array<int, array{tag:string,heading:string,uri:?string,subject_type:string}> $links
     * @return int number of links created
     */
    private function persistAuthorityLinks(int $ioId, array $links): int
    {
        if (empty($links)) {
            return 0;
        }
        try {
            if (! Schema::hasTable('library_item')
                || ! Schema::hasTable('library_subject_authority')
                || ! Schema::hasTable('library_item_authority_link')) {
                return 0;
            }
            $libraryItemId = DB::table('library_item')
                ->where('information_object_id', $ioId)
                ->value('id');
            if (! $libraryItemId) {
                return 0;
            }
            $libraryItemId = (int) $libraryItemId;
            $now = date('Y-m-d H:i:s');
            $created = 0;

            foreach ($links as $link) {
                $authorityId = $this->findOrCreateAuthority($link, $now);
                if ($authorityId === null) {
                    continue;
                }
                $exists = DB::table('library_item_authority_link')
                    ->where('library_item_id', $libraryItemId)
                    ->where('authority_id', $authorityId)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('library_item_authority_link')->insert([
                    'library_item_id' => $libraryItemId,
                    'authority_id'    => $authorityId,
                    'source_tag'      => $link['tag'],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                DB::table('library_subject_authority')
                    ->where('id', $authorityId)
                    ->increment('linked_count');
                $created++;
            }

            return $created;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Match an authority by uri (preferred) or heading, creating it if absent.
     *
     * @param array{tag:string,heading:string,uri:?string,subject_type:string} $link
     */
    private function findOrCreateAuthority(array $link, string $now): ?int
    {
        $uri = $link['uri'] ?: null;
        $heading = $link['heading'];

        $query = DB::table('library_subject_authority');
        if ($uri !== null) {
            $existing = (clone $query)->where('uri', $uri)->value('id');
            if ($existing) {
                return (int) $existing;
            }
        }
        $existing = (clone $query)
            ->where('heading', $heading)
            ->where('subject_type', $link['subject_type'])
            ->value('id');
        if ($existing) {
            // Backfill the uri if the incoming record now carries one.
            if ($uri !== null) {
                DB::table('library_subject_authority')
                    ->where('id', $existing)
                    ->whereNull('uri')
                    ->update(['uri' => $uri, 'updated_at' => $now]);
            }

            return (int) $existing;
        }

        return (int) DB::table('library_subject_authority')->insertGetId([
            'heading'      => $heading,
            'lc_label'     => $heading,
            'subject_type' => $link['subject_type'],
            'source'       => $uri !== null ? 'marc-import' : 'local',
            'uri'          => $uri,
            'linked_count' => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    private function matchExisting(string $identifier): ?int
    {
        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }
            // First try string match on io.identifier (most common round-trip)
            $id = DB::table('information_object')
                ->where('identifier', $identifier)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
            // Numeric 001 may be a raw io.id (export fallback path)
            if (ctype_digit($identifier)) {
                $hit = DB::table('information_object')->where('id', (int) $identifier)->value('id');
                if ($hit) {
                    return (int) $hit;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        return null;
    }

    /**
     * @return array{0: int, 1: string} [io_id, 'create'|'update']
     */
    private function persist(array $desc): array
    {
        $now = date('Y-m-d H:i:s');
        $existingId = $desc['matched_io_id'] ?? null;

        if ($existingId) {
            // UPDATE path - update information_object_i18n + identifier
            DB::table('information_object')
                ->where('id', $existingId)
                ->update([
                    'identifier' => $desc['control_number'] ?: null,
                ]);
            DB::table('information_object_i18n')
                ->updateOrInsert(
                    ['id' => $existingId, 'culture' => $this->culture],
                    [
                        'title' => $desc['title'],
                        'scope_and_content' => $desc['scope_and_content'],
                        'extent_and_medium' => $desc['extent_and_medium'],
                        'archival_history' => $desc['archival_history'],
                        'acquisition' => $desc['acquisition'],
                        'reproduction_conditions' => $desc['reproduction_conditions'],
                        'access_conditions' => $desc['access_conditions'],
                        'related_units_of_description' => $desc['related_units_of_description'],
                    ]
                );
            DB::table('object')->where('id', $existingId)->update(['updated_at' => $now]);
            return [(int) $existingId, 'update'];
        }

        // CREATE path - insert object, information_object, i18n row.
        // Skip cleanly if the schema isn't installed in this environment
        // (unit tests on stubbed databases hit this path).
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
            'source_culture' => $this->culture,
        ]);
        DB::table('information_object')->insert([
            'id' => $objectId,
            'identifier' => $desc['control_number'] ?: null,
            'source_culture' => $this->culture,
            'lft' => 0,
            'rgt' => 0,
        ]);
        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => $this->culture,
            'title' => $desc['title'],
            'scope_and_content' => $desc['scope_and_content'],
            'extent_and_medium' => $desc['extent_and_medium'],
            'archival_history' => $desc['archival_history'],
            'acquisition' => $desc['acquisition'],
            'reproduction_conditions' => $desc['reproduction_conditions'],
            'access_conditions' => $desc['access_conditions'],
            'related_units_of_description' => $desc['related_units_of_description'],
        ]);

        return [(int) $objectId, 'create'];
    }

    private function logAudit(int $ioId, string $action, array $desc): ?int
    {
        if ($this->auditLoggerClass === null) {
            return null;
        }
        try {
            $cls = $this->auditLoggerClass;
            /** @var object $logger */
            $logger = new $cls();
            $payload = [
                'source' => 'marcxml-import',
                'control_number' => $desc['control_number'],
                'title' => $desc['title'],
                'subjects' => $desc['subjects'] ?? [],
                'creators' => $desc['creators'] ?? [],
            ];
            return $logger->logAction(
                action: 'marcxml_'.$action,
                entityType: 'information_object',
                entityId: $ioId,
                metadata: $payload,
                entityTitle: $desc['title'] ?? null,
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<int, string> */
    private function collectLibxmlErrors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $err) {
            $errors[] = sprintf('line %d: %s', $err->line, trim($err->message));
        }
        return $errors;
    }
}
