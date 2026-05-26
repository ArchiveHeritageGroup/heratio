<?php

/**
 * RadXmlImporter - parse a RAD XML document and upsert the matching
 * row in `ahg_io_rad`. Round-trips with RadSerializer.
 *
 * Two-phase API mirroring MarcXmlImporter:
 *   $importer = new RadXmlImporter();
 *   $preview  = $importer->preview($xml, $culture);   // dry-run
 *   $result   = $importer->commit($xml, $culture);    // writes
 *
 * Matching strategy: `<identifier>` is looked up against
 * `information_object.identifier`. When no IO matches, the record is
 * skipped (not silently created in `information_object` because RAD
 * imports normally target existing descriptions).
 *
 * Issue #662 Phase 3.
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

use AhgMetadataExport\Services\Exporters\RadSerializer;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RadXmlImporter
{
    public const NS = RadSerializer::NAMESPACE_URI;

    private string $sidecarTable = 'ahg_io_rad';

    private array $extraElements = [
        'dateOfDescriptions' => 'date_of_descriptions',
        'descriptionStatus' => 'status',
        'levelOfDetail' => 'level_of_detail',
    ];

    public function preview(string $xml, string $culture = 'en'): array
    {
        return $this->process($xml, $culture, false);
    }

    public function commit(string $xml, string $culture = 'en'): array
    {
        return $this->process($xml, $culture, true);
    }

    private function process(string $xml, string $culture, bool $write): array
    {
        $records = $this->parseDocument($xml);
        $results = [];
        foreach ($records as $rec) {
            $identifier = trim((string) ($rec['identifier'] ?? ''));
            $result = [
                'identifier' => $identifier,
                'matched_io_id' => null,
                'will_update' => false,
                'will_create_sidecar' => false,
                'warnings' => [],
                'fields' => $rec['fields'],
            ];

            if ($identifier === '') {
                $result['warnings'][] = 'identifier element missing or empty; record skipped';
                $results[] = $result;
                continue;
            }

            $ioId = $this->matchIo($identifier);
            if (! $ioId) {
                $result['warnings'][] = 'no information_object row matches identifier "'.$identifier.'"';
                $results[] = $result;
                continue;
            }
            $result['matched_io_id'] = $ioId;

            try {
                $sidecarExists = Schema::hasTable($this->sidecarTable)
                    ? DB::table($this->sidecarTable)->where('information_object_id', $ioId)->exists()
                    : false;
            } catch (\Throwable $e) {
                $sidecarExists = false;
            }

            $result['will_update'] = $sidecarExists;
            $result['will_create_sidecar'] = ! $sidecarExists;

            if ($write) {
                try {
                    $this->upsert($ioId, $rec['fields']);
                    $result['upserted'] = true;
                } catch (\Throwable $e) {
                    $result['warnings'][] = 'upsert failed: '.$e->getMessage();
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    private function parseDocument(string $xml): array
    {
        $records = [];
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $loaded || ! $doc->documentElement) {
            return $records;
        }

        // Support either a single <radDescription> root or a wrapper
        // <radCollection> with multiple children.
        $candidates = [];
        if ($doc->documentElement->localName === 'radDescription') {
            $candidates[] = $doc->documentElement;
        } else {
            foreach ($doc->documentElement->getElementsByTagNameNS(self::NS, 'radDescription') as $node) {
                $candidates[] = $node;
            }
            // Tolerate documents without the namespace declaration.
            if (empty($candidates)) {
                foreach ($doc->documentElement->getElementsByTagName('radDescription') as $node) {
                    $candidates[] = $node;
                }
            }
        }

        foreach ($candidates as $node) {
            $records[] = $this->parseRecord($node);
        }

        return $records;
    }

    private function parseRecord(DOMElement $node): array
    {
        $rec = ['identifier' => '', 'fields' => []];

        $identifier = $this->childText($node, 'identifier');
        if ($identifier !== null) {
            $rec['identifier'] = $identifier;
        }

        foreach (RadSerializer::FIELD_MAP as $element => $cols) {
            $value = $this->childText($node, $element);
            if ($value !== null && $value !== '') {
                $rec['fields'][$cols[0]] = $value;
            }
        }

        foreach ($this->extraElements as $element => $col) {
            $value = $this->childText($node, $element);
            if ($value !== null && $value !== '') {
                $rec['fields'][$col] = $value;
            }
        }

        return $rec;
    }

    private function childText(DOMElement $parent, string $localName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return trim((string) $child->textContent);
            }
        }

        return null;
    }

    private function matchIo(string $identifier): ?int
    {
        try {
            $id = DB::table('information_object')->where('identifier', $identifier)->value('id');
        } catch (\Throwable $e) {
            return null;
        }
        if ($id) {
            return (int) $id;
        }
        if (str_starts_with($identifier, 'heratio-io-')) {
            $tail = substr($identifier, strlen('heratio-io-'));
            if (ctype_digit($tail)) {
                return (int) $tail;
            }
        }

        return null;
    }

    private function upsert(int $ioId, array $fields): void
    {
        if (! Schema::hasTable($this->sidecarTable)) {
            throw new \RuntimeException('ahg_io_rad table is not installed');
        }
        $fields['information_object_id'] = $ioId;
        $fields['updated_at'] = now();

        $existing = DB::table($this->sidecarTable)->where('information_object_id', $ioId)->first();
        if ($existing) {
            DB::table($this->sidecarTable)
                ->where('information_object_id', $ioId)
                ->update($fields);
        } else {
            $fields['created_at'] = $fields['created_at'] ?? now();
            DB::table($this->sidecarTable)->insert($fields);
        }
    }
}
