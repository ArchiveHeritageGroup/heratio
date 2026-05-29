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
     * Build a single manifest metadata row for the camera make + model,
     * read from the EXIF block (typically `digital_object_metadata.raw_metadata`).
     * The value is formatted "Make Model" (AtoM parity). Returns null when
     * neither Make nor Model is present.
     *
     * EXIF tags may sit at the top of raw_metadata, nested under "exif" /
     * "EXIF", or carry an "EXIF:" prefix - we probe all four shapes for
     * each of Make / Model independently.
     *
     * @param array|null $exif Parsed raw_metadata JSON, may be null.
     * @return array{label:array,value:array}|null
     */
    public static function fromCamera(?array $exif): ?array
    {
        if (!is_array($exif)) {
            return null;
        }

        $make = self::probeExif($exif, 'Make');
        $model = self::probeExif($exif, 'Model');

        $camera = trim(($make ?? '') . ' ' . ($model ?? ''));
        if ($camera === '') {
            return null;
        }

        return [
            'label' => ['en' => ['Camera']],
            'value' => ['en' => [$camera]],
        ];
    }

    /**
     * Build a single manifest metadata row for GPS coordinates, read from
     * the EXIF block. Mirrors AtoM: emits a decimal "lat, long" string at
     * six decimal places.
     *
     * Accepts either an already-consolidated decimal pair
     * (`latitude` / `longitude`, or a pre-built `decimal` string under a
     * `gps` sub-array) OR raw EXIF GPSLatitude / GPSLongitude rationals
     * with their N/S/E/W reference tags, which we convert to decimal.
     * Returns null when no usable coordinate pair is present.
     *
     * @param array|null $exif Parsed raw_metadata JSON, may be null.
     * @return array{label:array,value:array}|null
     */
    public static function fromGpsCoordinates(?array $exif): ?array
    {
        if (!is_array($exif)) {
            return null;
        }

        // 1. Pre-built decimal string from the extractor (`gps.decimal`).
        $decimal = self::nonEmptyString($exif['gps']['decimal'] ?? ($exif['decimal'] ?? null));
        if ($decimal !== null) {
            return [
                'label' => ['en' => ['GPS Coordinates']],
                'value' => ['en' => [$decimal]],
            ];
        }

        // 2. Already-consolidated numeric latitude / longitude pair.
        $lat = self::toFloatOrNull($exif['gps']['latitude'] ?? ($exif['latitude'] ?? null));
        $lon = self::toFloatOrNull($exif['gps']['longitude'] ?? ($exif['longitude'] ?? null));

        // 3. Raw EXIF GPS rationals + hemisphere refs.
        if ($lat === null || $lon === null) {
            $rawLat = $exif['GPSLatitude'] ?? $exif['exif']['GPSLatitude'] ?? $exif['EXIF']['GPSLatitude'] ?? null;
            $rawLon = $exif['GPSLongitude'] ?? $exif['exif']['GPSLongitude'] ?? $exif['EXIF']['GPSLongitude'] ?? null;
            if ($rawLat !== null && $rawLon !== null) {
                $latRef = $exif['GPSLatitudeRef'] ?? $exif['exif']['GPSLatitudeRef'] ?? $exif['EXIF']['GPSLatitudeRef'] ?? 'N';
                $lonRef = $exif['GPSLongitudeRef'] ?? $exif['exif']['GPSLongitudeRef'] ?? $exif['EXIF']['GPSLongitudeRef'] ?? 'E';
                $lat = self::gpsToDecimal($rawLat, (string) $latRef);
                $lon = self::gpsToDecimal($rawLon, (string) $lonRef);
            }
        }

        if ($lat === null || $lon === null) {
            return null;
        }

        return [
            'label' => ['en' => ['GPS Coordinates']],
            'value' => ['en' => [sprintf('%.6f, %.6f', $lat, $lon)]],
        ];
    }

    /**
     * Build a single manifest metadata row for the place where the item
     * was captured / described, formatted "City, State, Country" (AtoM
     * parity). Accepts a flattened IPTC sidecar row or a nested
     * consolidated `location` sub-array.
     *
     * Column-name tolerance: state may arrive as `state`, `province_state`
     * (IPTC Core), or `province`. Returns null when no part is present.
     *
     * @param array $source IPTC sidecar row or consolidated metadata array.
     * @return array{label:array,value:array}|null
     */
    public static function fromLocation(array $source): ?array
    {
        // Allow callers to pass either a flat row or a {location: {...}} nest.
        $loc = is_array($source['location'] ?? null) ? $source['location'] : $source;

        $city = self::nonEmptyString($loc['city'] ?? null);
        $state = self::nonEmptyString(
            $loc['state'] ?? ($loc['province_state'] ?? ($loc['province'] ?? null))
        );
        $country = self::nonEmptyString($loc['country'] ?? null);

        $parts = array_values(array_filter([$city, $state, $country], static fn ($p) => $p !== null));
        if (empty($parts)) {
            return null;
        }

        return [
            'label' => ['en' => ['Location']],
            'value' => ['en' => [implode(', ', $parts)]],
        ];
    }

    /**
     * Build a standalone "Copyright" manifest metadata row from the IPTC
     * copyright notice (IPTC 2:116). Unlike buildRequiredStatement(), this
     * is unconditional - it surfaces the copyright as a discrete metadata
     * row regardless of any ISAD-level rights statement, matching AtoM,
     * which emits Copyright both in requiredStatement AND as its own row.
     *
     * Returns null when the IPTC carries no copyright notice.
     *
     * @param array $iptc Associative array shaped like a dam_iptc_metadata row.
     * @return array{label:array,value:array}|null
     */
    public static function buildCopyrightMetadata(array $iptc): ?array
    {
        $copyright = self::nonEmptyString($iptc['copyright_notice'] ?? null);
        if ($copyright === null) {
            return null;
        }

        return [
            'label' => ['en' => ['Copyright']],
            'value' => ['en' => [$copyright]],
        ];
    }

    /**
     * Probe an EXIF tag across the four shapes raw_metadata may take:
     * top-level, nested under "exif" / "EXIF", or "EXIF:"-prefixed.
     * Returns the first non-empty trimmed string, or null.
     */
    private static function probeExif(array $exif, string $tag): ?string
    {
        foreach ([
            $exif[$tag] ?? null,
            $exif['exif'][$tag] ?? null,
            $exif['EXIF'][$tag] ?? null,
            $exif['EXIF:' . $tag] ?? null,
        ] as $candidate) {
            $s = self::nonEmptyString($candidate);
            if ($s !== null) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Coerce a value to float, or null when it is null / non-numeric.
     */
    private static function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    /**
     * Convert an EXIF GPS coordinate (degrees / minutes / seconds) to a
     * signed decimal degree value, applying the hemisphere reference.
     *
     * Accepts the coordinate as either:
     *   - an array of three rationals ["deg/1", "min/1", "sec/100"] or
     *     three numeric DMS components, or
     *   - an already-decimal numeric scalar.
     *
     * Returns null when the value cannot be parsed.
     */
    private static function gpsToDecimal(mixed $coord, string $ref): ?float
    {
        if (is_numeric($coord)) {
            $decimal = (float) $coord;
        } elseif (is_array($coord) && count($coord) >= 3) {
            $deg = self::parseRational($coord[0]);
            $min = self::parseRational($coord[1]);
            $sec = self::parseRational($coord[2]);
            if ($deg === null || $min === null || $sec === null) {
                return null;
            }
            $decimal = $deg + ($min / 60) + ($sec / 3600);
        } else {
            return null;
        }

        $ref = strtoupper(trim($ref));
        if ($ref === 'S' || $ref === 'W') {
            $decimal = -$decimal;
        }
        return $decimal;
    }

    /**
     * Parse an EXIF rational ("num/den") or a plain numeric value into a
     * float. Returns null on malformed input or division by zero.
     */
    private static function parseRational(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = array_pad(explode('/', $value, 2), 2, '1');
            if (!is_numeric($num) || !is_numeric($den) || (float) $den === 0.0) {
                return null;
            }
            return (float) $num / (float) $den;
        }
        return null;
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
