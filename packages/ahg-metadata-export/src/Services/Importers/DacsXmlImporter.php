<?php

/**
 * DacsXmlImporter - parse a DACS XML document and upsert the matching
 * row in `ahg_io_dacs`. Round-trips with DacsSerializer.
 *
 * Two-phase API mirroring MarcXmlImporter / RadXmlImporter.
 *
 * Matching strategy: prefers `<recordIdentifier>`, falls back to
 * `<referenceCode>`. When neither matches an existing information_object
 * the record is skipped (sidecar imports never create new IOs).
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

use AhgMetadataExport\Services\Exporters\DacsSerializer;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DacsXmlImporter
{
    public const NS = DacsSerializer::NAMESPACE_URI;

    private string $sidecarTable = 'ahg_io_dacs';

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
            $recordIdentifier = trim((string) ($rec['recordIdentifier'] ?? ''));
            $referenceCode = trim((string) ($rec['fields']['reference_code'] ?? ''));
            $result = [
                'record_identifier' => $recordIdentifier,
                'reference_code' => $referenceCode,
                'matched_io_id' => null,
                'will_update' => false,
                'will_create_sidecar' => false,
                'warnings' => [],
                'fields' => $rec['fields'],
            ];

            $ioId = null;
            if ($recordIdentifier !== '') {
                $ioId = $this->matchIo($recordIdentifier);
            }
            if (! $ioId && $referenceCode !== '') {
                $ioId = $this->matchIo($referenceCode);
            }

            if (! $ioId) {
                $result['warnings'][] = 'no information_object row matches recordIdentifier or referenceCode';
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

        $candidates = [];
        if ($doc->documentElement->localName === 'dacsDescription') {
            $candidates[] = $doc->documentElement;
        } else {
            foreach ($doc->documentElement->getElementsByTagNameNS(self::NS, 'dacsDescription') as $node) {
                $candidates[] = $node;
            }
            if (empty($candidates)) {
                foreach ($doc->documentElement->getElementsByTagName('dacsDescription') as $node) {
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
        $rec = ['recordIdentifier' => '', 'fields' => []];
        $rid = $this->childText($node, 'recordIdentifier');
        if ($rid !== null) {
            $rec['recordIdentifier'] = $rid;
        }
        foreach (DacsSerializer::FIELD_MAP as $element => $cols) {
            $value = $this->childText($node, $element);
            if ($value !== null && $value !== '') {
                $rec['fields'][$cols[0]] = $value;
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

    private function matchIo(string $value): ?int
    {
        try {
            $id = DB::table('information_object')->where('identifier', $value)->value('id');
        } catch (\Throwable $e) {
            return null;
        }
        if ($id) {
            return (int) $id;
        }
        if (str_starts_with($value, 'heratio-io-')) {
            $tail = substr($value, strlen('heratio-io-'));
            if (ctype_digit($tail)) {
                return (int) $tail;
            }
        }

        return null;
    }

    private function upsert(int $ioId, array $fields): void
    {
        if (! Schema::hasTable($this->sidecarTable)) {
            throw new \RuntimeException('ahg_io_dacs table is not installed');
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
