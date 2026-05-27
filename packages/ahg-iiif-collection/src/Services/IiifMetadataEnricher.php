<?php

/**
 * IiifMetadataEnricher - IPTC / EXIF / XMP -> IIIF Presentation manifest metadata.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgIiifCollection\Services;

/**
 * Pure transformer that turns a `dam_iptc_metadata` row (and the
 * `digital_object_metadata.raw_metadata` EXIF blob) into IIIF Presentation
 * API 3.0 manifest fragments: a list of `metadata` rows, an optional
 * `requiredStatement` body, and a `provider.label` candidate when no other
 * creator is set.
 *
 * Issue #748. The enricher is intentionally framework-free + side-effect
 * free so it can be unit-tested without a Laravel container and so the
 * legacy v2 manifest emitter can reuse `fromIptc()` without picking up
 * `IiifCollectionService`'s DB deps.
 *
 * IPTC field naming follows the IPTC Core 1.3 schema. The Heratio table
 * stores `creator` (IPTC tag 2:80, also known as "byline") and
 * `copyright_notice` (IPTC tag 2:116) under their IPTC names.
 */
class IiifMetadataEnricher
{
    /**
     * Build the manifest `metadata` rows that come from an IPTC sidecar
     * row. Returns an array of { label, value } shapes ready to be
     * merged into `$manifest['metadata']`.
     *
     * Empty / missing inputs return [] - callers always pass through
     * whatever they get from the DB without null-guarding first.
     *
     * @param array $iptc Associative array shaped like a dam_iptc_metadata row.
     * @return array<int,array{label:array,value:array}>
     */
    public static function fromIptc(array $iptc): array
    {
        $rows = [];

        // IPTC 2:80 "By-line" -> Creator. The Heratio column is named
        // `creator` to match the IPTC Core 1.3 element name.
        $byline = self::nonEmptyString($iptc['creator'] ?? null);
        if ($byline !== null) {
            $rows[] = [
                'label' => ['en' => ['Creator']],
                'value' => ['en' => [$byline]],
            ];
        }

        // IPTC 2:25 "Keywords" - stored as either a delimited string
        // (comma / semicolon / pipe / newline) or already a JSON array.
        // We normalise to an array of trimmed non-empty strings.
        $keywords = self::splitKeywords($iptc['keywords'] ?? null);
        if (!empty($keywords)) {
            $rows[] = [
                'label' => ['en' => ['Keywords']],
                'value' => ['en' => $keywords],
            ];
        }

        return $rows;
    }

    /**
     * Build a manifest `requiredStatement` body from the IPTC copyright
     * notice, but only when the IO has no explicit rights_statement
     * already (per the IIIF spec the manifest carries at most one
     * requiredStatement, and explicit ISAD rights from the description
     * must win over inferred file-level IPTC values).
     *
     * Returns null when no statement should be emitted.
     *
     * @param array $iptc Associative array shaped like a dam_iptc_metadata row.
     * @param string|null $ioRightsStatement Existing IO-level rights statement.
     * @return array{label:array,value:array}|null
     */
    public static function buildRequiredStatement(array $iptc, ?string $ioRightsStatement = null): ?array
    {
        // ISAD-level rights win - if the IO already supplies a rights /
        // reproduction-conditions value, we do NOT overwrite with the
        // file-level IPTC copyright notice.
        if (self::nonEmptyString($ioRightsStatement) !== null) {
            return null;
        }

        $copyright = self::nonEmptyString($iptc['copyright_notice'] ?? null);
        if ($copyright === null) {
            return null;
        }

        return [
            'label' => ['en' => ['Attribution']],
            'value' => ['en' => [$copyright]],
        ];
    }

    /**
     * Build a single manifest metadata row for the EXIF
     * DateTimeOriginal tag, when the IO has no archival `dateCreated`
     * value of its own. ISAD-level dates take precedence; only when
     * the description carries no creation date do we surface the
     * camera-set capture timestamp.
     *
     * Accepts the parsed `raw_metadata` array (typically read from
     * `digital_object_metadata.raw_metadata` JSON column). Returns
     * null when nothing should be emitted.
     *
     * @param array|null $exif Parsed raw_metadata JSON, may be null.
     * @param bool $ioHasDateCreated Whether the IO already has an ISAD date.
     * @return array{label:array,value:array}|null
     */
    public static function fromExifDateTimeOriginal(?array $exif, bool $ioHasDateCreated): ?array
    {
        if ($ioHasDateCreated) {
            return null;
        }
        if (!is_array($exif)) {
            return null;
        }

        // EXIF tags are typically nested under "exif" or "EXIF", but
        // some extractors flatten them onto the top of raw_metadata.
        // We probe both shapes.
        $candidates = [
            $exif['DateTimeOriginal'] ?? null,
            $exif['exif']['DateTimeOriginal'] ?? null,
            $exif['EXIF']['DateTimeOriginal'] ?? null,
            $exif['EXIF:DateTimeOriginal'] ?? null,
        ];

        foreach ($candidates as $c) {
            $dt = self::nonEmptyString($c);
            if ($dt !== null) {
                return [
                    'label' => ['en' => ['Date of capture']],
                    'value' => ['en' => [$dt]],
                ];
            }
        }
        return null;
    }

    /**
     * Convenience: return the IPTC byline (creator) so a caller can
     * promote it into `provider[0].label` when the manifest has no
     * other creator/agent set. Returns null when absent.
     */
    public static function bylineFromIptc(array $iptc): ?string
    {
        return self::nonEmptyString($iptc['creator'] ?? null);
    }

    /**
     * Trim + reject empty / whitespace-only values. Returns null when
     * the input is null, not a scalar, or trims to an empty string.
     */
    private static function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    /**
     * Normalise the IPTC keywords field into an array of trimmed,
     * non-empty terms. Accepts:
     *   - PHP arrays (already parsed)
     *   - JSON-encoded arrays
     *   - delimited strings (`,`, `;`, `|`, or newlines)
     *
     * Returns [] when input is empty or malformed.
     *
     * @return array<int,string>
     */
    private static function splitKeywords(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_array($raw)) {
            $items = $raw;
        } else {
            if (!is_scalar($raw)) {
                return [];
            }
            $s = trim((string) $raw);
            if ($s === '') {
                return [];
            }

            // JSON-encoded array? Try to decode; fall back to string split.
            if ($s[0] === '[') {
                $decoded = json_decode($s, true);
                if (is_array($decoded)) {
                    $items = $decoded;
                } else {
                    $items = preg_split('/[,;|\r\n]+/', $s) ?: [];
                }
            } else {
                $items = preg_split('/[,;|\r\n]+/', $s) ?: [];
            }
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $t = trim((string) $item);
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out;
    }
}
