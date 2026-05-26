<?php

/**
 * RdaCarrierMapper - resolve MARC21 RDA 336 (content type), 337 (media type)
 * and 338 (carrier type) from a digital_object MIME type or a physical carrier
 * code. Used by both the MARCXML serializer (export side) and importer
 * (round-trip preservation).
 *
 * The mapping table ahg_marc_rda_mapping is operator-extensible: ship sane
 * defaults via install.sql, then let local cataloguers override per
 * jurisdiction (e.g. a national archive that prefers "performed music" for
 * audio over "spoken word", or wants a custom rdacarrier vocabulary).
 *
 * Resolution rules (in order):
 *   1. If MIME provided: try mime_exact match first, then most-specific
 *      mime_prefix match (longest prefix wins).
 *   2. If carrier code provided: exact match on match_kind='carrier'.
 *   3. Fallback row with match_value='*' / match_kind='mime_exact'.
 *   4. Hard-coded default if the table is empty / missing.
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

namespace AhgMetadataExport\Services\Rda;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RdaCarrierMapper
{
    /** @var array<int, object>|null lazily cached active mapping rows */
    private ?array $rowsCache = null;

    /**
     * Resolve RDA mapping for a digital object given its MIME type.
     *
     * @return array{336: array{a: string, '2': string}, 337: array{a: string, '2': string}, 338: array{a: string, '2': string}}
     */
    public function mapByMime(?string $mime): array
    {
        $mime = strtolower(trim((string) $mime));
        $rows = $this->loadRows();

        // 1. mime_exact wins outright
        foreach ($rows as $row) {
            if ($row->match_kind === 'mime_exact' && $row->match_value === $mime && $mime !== '') {
                return $this->rowToTriple($row);
            }
        }

        // 2. mime_prefix - longest prefix wins
        $best = null;
        $bestLen = -1;
        foreach ($rows as $row) {
            if ($row->match_kind !== 'mime_prefix') {
                continue;
            }
            $prefix = $row->match_value;
            if ($mime !== '' && str_starts_with($mime, $prefix) && strlen($prefix) > $bestLen) {
                $best = $row;
                $bestLen = strlen($prefix);
            }
        }
        if ($best !== null) {
            return $this->rowToTriple($best);
        }

        // 3. fallback catch-all row
        foreach ($rows as $row) {
            if ($row->match_kind === 'mime_exact' && $row->match_value === '*') {
                return $this->rowToTriple($row);
            }
        }

        // 4. final hard-coded default
        return $this->hardDefault();
    }

    /**
     * Resolve RDA mapping for a physical-object IO given its carrier code
     * (e.g. "volume", "sheet", "audio-disc"). Falls back to "unmediated /
     * volume" when no match is found.
     *
     * @return array{336: array{a: string, '2': string}, 337: array{a: string, '2': string}, 338: array{a: string, '2': string}}
     */
    public function mapByCarrier(?string $carrier): array
    {
        $carrier = strtolower(trim((string) $carrier));
        if ($carrier === '') {
            return $this->physicalDefault();
        }
        $rows = $this->loadRows();
        foreach ($rows as $row) {
            if ($row->match_kind === 'carrier' && $row->match_value === $carrier) {
                return $this->rowToTriple($row);
            }
        }

        return $this->physicalDefault();
    }

    /**
     * Reverse-lookup: given the 338 carrier term parsed out of an inbound
     * MARCXML record, find the matching digital-object MIME prefix that we
     * would have emitted. Used by the importer to flag mismatches.
     */
    public function carrierTermToMimePrefix(?string $carrierTerm): ?string
    {
        $carrierTerm = strtolower(trim((string) $carrierTerm));
        if ($carrierTerm === '') {
            return null;
        }
        foreach ($this->loadRows() as $row) {
            if (strtolower((string) $row->carrier_type_term) === $carrierTerm
                && $row->match_kind === 'mime_prefix') {
                return $row->match_value;
            }
        }

        return null;
    }

    private function rowToTriple(object $row): array
    {
        return [
            336 => [
                'a' => (string) ($row->content_type_term ?: 'unspecified'),
                '2' => (string) ($row->content_type_source ?: 'rdacontent'),
            ],
            337 => [
                'a' => (string) ($row->media_type_term ?: 'unspecified'),
                '2' => (string) ($row->media_type_source ?: 'rdamedia'),
            ],
            338 => [
                'a' => (string) ($row->carrier_type_term ?: 'other'),
                '2' => (string) ($row->carrier_type_source ?: 'rdacarrier'),
            ],
        ];
    }

    private function hardDefault(): array
    {
        return [
            336 => ['a' => 'computer dataset', '2' => 'rdacontent'],
            337 => ['a' => 'computer', '2' => 'rdamedia'],
            338 => ['a' => 'online resource', '2' => 'rdacarrier'],
        ];
    }

    private function physicalDefault(): array
    {
        return [
            336 => ['a' => 'text', '2' => 'rdacontent'],
            337 => ['a' => 'unmediated', '2' => 'rdamedia'],
            338 => ['a' => 'volume', '2' => 'rdacarrier'],
        ];
    }

    /**
     * @return array<int, object>
     */
    private function loadRows(): array
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }
        try {
            if (! Schema::hasTable('ahg_marc_rda_mapping')) {
                return $this->rowsCache = [];
            }
            $this->rowsCache = DB::table('ahg_marc_rda_mapping')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            $this->rowsCache = [];
        }

        return $this->rowsCache;
    }
}
