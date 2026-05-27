<?php

/**
 * PiiScanService - regex + Luhn based PII detector for Heratio privacy Phase 1.
 *
 * Issue #669 Phase 1. Detects email, phone, national_id, credit_card, ip,
 * date_of_birth in free text. No LLM dependency - pattern-based only so it
 * runs offline and inside the audit hot-path. Per-jurisdiction sensitivity
 * via ahg_setting.privacy_jurisdiction (gdpr, popia, uk_gdpr, ccpa).
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

declare(strict_types=1);

namespace AhgPrivacy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detected PII finding.
 *
 *  - type:         email | phone | national_id | credit_card | ip | date_of_birth
 *  - value:        the matched substring (raw)
 *  - offset_start: byte offset (PREG_OFFSET_CAPTURE)
 *  - offset_end:   byte offset (start + length)
 *  - confidence:   0.0 - 1.0, regex-only signals settle around 0.7; Luhn-validated
 *                  credit cards bump to 0.95; jurisdiction-specific id-checksum
 *                  matches reach 0.9
 *
 * Returned as a plain array (not a value object) so the result is JSON-encodable
 * for direct storage in ahg_pii_scan_report.findings without further marshalling.
 */
final class PiiScanService
{
    /** Hard cap on findings returned per scan to keep memory bounded on large blobs. */
    public const MAX_FINDINGS = 500;

    /** @var array<string,array{pattern:string,confidence:float}> */
    private const BASE_PATTERNS = [
        // RFC 5322 simplified - good enough for catalog content; we DON'T try to
        // validate the local-part exhaustively because false negatives are worse
        // than mild false positives in a PII scan.
        'email' => [
            'pattern'    => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,24}/',
            'confidence' => 0.92,
        ],
        // IPv4. IPv6 is handled separately (different regex).
        'ip' => [
            'pattern'    => '/\b(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\b/',
            'confidence' => 0.85,
        ],
        // IPv6 - 8 groups, with optional :: shorthand.
        'ip_v6' => [
            'pattern'    => '/\b(?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}\b|\b(?:[0-9A-Fa-f]{1,4}:){1,7}:|\b::(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4}\b/',
            'confidence' => 0.80,
        ],
        // Date of birth. Loose ISO + DMY + MDY ranges. We narrow with a sanity
        // window (1900..current year) in the finder code so 1066 doesn't match.
        'date_of_birth' => [
            'pattern'    => '/\b(?:(?:19[0-9]{2}|20[0-2][0-9])[-\/](?:0?[1-9]|1[0-2])[-\/](?:0?[1-9]|[12][0-9]|3[01])|(?:0?[1-9]|[12][0-9]|3[01])[-\/](?:0?[1-9]|1[0-2])[-\/](?:19[0-9]{2}|20[0-2][0-9]))\b/',
            'confidence' => 0.55,
        ],
        // Credit-card candidate (Luhn validation done after match).
        'credit_card' => [
            'pattern'    => '/\b(?:\d[ -]?){12,18}\d\b/',
            'confidence' => 0.60, // bumped to 0.95 after Luhn success
        ],
    ];

    /**
     * Phone-number patterns by jurisdiction. The base regex tries E.164 first
     * (+CC followed by 7-14 digits) then falls back to local formats. Each
     * jurisdiction overrides the local fallback so SA gets 0XX patterns, UK
     * gets 0XXX, etc.
     *
     * @var array<string,array<string,array{pattern:string,confidence:float}>>
     */
    private const PHONE_PATTERNS = [
        'gdpr' => [
            'e164'  => ['pattern' => '/\+\d{1,3}[\s\-]?\(?\d{1,4}\)?[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/', 'confidence' => 0.85],
            'local' => ['pattern' => '/\b0\d{1,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/', 'confidence' => 0.70],
        ],
        'popia' => [
            'e164'  => ['pattern' => '/\+27[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{4}\b/', 'confidence' => 0.95],
            'local' => ['pattern' => '/\b0\d{2}[\s\-]?\d{3}[\s\-]?\d{4}\b/', 'confidence' => 0.80],
        ],
        'uk_gdpr' => [
            'e164'  => ['pattern' => '/\+44[\s\-]?\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/', 'confidence' => 0.95],
            'local' => ['pattern' => '/\b0\d{3,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/', 'confidence' => 0.75],
        ],
        'ccpa' => [
            'e164'  => ['pattern' => '/\+1[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{4}\b/', 'confidence' => 0.95],
            'local' => ['pattern' => '/\b\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{4}\b/', 'confidence' => 0.65],
        ],
    ];

    /**
     * National ID patterns by jurisdiction.
     *  - popia:   SA ID number, 13 digits, Luhn-style checksum (mod 10 on doubled odds)
     *  - uk_gdpr: UK NINO, AA##  ####  C
     *  - ccpa:    US SSN, ###-##-####
     *  - gdpr:    no single canonical pattern; we leave it empty (consumers can scan
     *             jurisdiction='popia' or 'uk_gdpr' for member-state coverage).
     *
     * @var array<string,array{pattern:string,confidence:float,validator:?string}>
     */
    private const NATIONAL_ID_PATTERNS = [
        'popia' => [
            'pattern'    => '/\b\d{13}\b/',
            'confidence' => 0.70,
            'validator'  => 'validateSaIdNumber',
        ],
        'uk_gdpr' => [
            'pattern'    => '/\b[A-CEGHJ-PR-TW-Z]{2}\s?\d{2}\s?\d{2}\s?\d{2}\s?[A-D]\b/',
            'confidence' => 0.90,
            'validator'  => null,
        ],
        'ccpa' => [
            'pattern'    => '/\b\d{3}-\d{2}-\d{4}\b/',
            'confidence' => 0.85,
            'validator'  => null,
        ],
    ];

    private string $jurisdiction;

    public function __construct(?string $jurisdiction = null)
    {
        $this->jurisdiction = $jurisdiction ?: $this->resolveJurisdiction();
    }

    /**
     * Scan free text and return all PII findings.
     *
     * @return array<int,array{type:string,value:string,offset_start:int,offset_end:int,confidence:float}>
     */
    public function scan(string $text): array
    {
        $findings = [];

        $this->collectEmails($text, $findings);
        $this->collectPhones($text, $findings);
        $this->collectIps($text, $findings);
        $this->collectNationalIds($text, $findings);
        $this->collectCreditCards($text, $findings);
        $this->collectDatesOfBirth($text, $findings);

        // Stable ordering: by offset_start ascending, then confidence descending.
        usort($findings, static function (array $a, array $b): int {
            if ($a['offset_start'] === $b['offset_start']) {
                return $b['confidence'] <=> $a['confidence'];
            }
            return $a['offset_start'] <=> $b['offset_start'];
        });

        // Deduplicate exact overlapping spans, keeping the higher-confidence finding.
        $findings = $this->dedupeOverlaps($findings);

        if (count($findings) > self::MAX_FINDINGS) {
            $findings = array_slice($findings, 0, self::MAX_FINDINGS);
        }

        return $findings;
    }

    /**
     * Run a scan and persist a row in ahg_pii_scan_report.
     *
     * @return int|null inserted report id, or null when the schema is not ready
     */
    public function scanAndPersist(string $text, ?int $informationObjectId = null, ?int $userId = null): ?int
    {
        if (! Schema::hasTable('ahg_pii_scan_report')) {
            return null;
        }

        $startedAt = date('Y-m-d H:i:s');
        $findings = $this->scan($text);
        $finishedAt = date('Y-m-d H:i:s');

        $countsByType = [];
        foreach ($findings as $f) {
            $type = $f['type'];
            $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
        }

        // Cap stored findings to the same MAX_FINDINGS - already capped by scan(),
        // but defence in depth in case the contract drifts.
        $storedFindings = array_slice($findings, 0, self::MAX_FINDINGS);

        return (int) DB::table('ahg_pii_scan_report')->insertGetId([
            'information_object_id' => $informationObjectId,
            'scan_started_at'       => $startedAt,
            'scan_finished_at'      => $finishedAt,
            'hits_total'            => count($findings),
            'hits_by_type'          => json_encode($countsByType, JSON_UNESCAPED_SLASHES),
            'findings'              => json_encode($storedFindings, JSON_UNESCAPED_SLASHES),
            'jurisdiction'          => $this->jurisdiction,
            'status'                => 'pending',
            'scanned_by_user_id'    => $userId,
            'created_at'            => $startedAt,
            'updated_at'            => $finishedAt,
        ]);
    }

    public function jurisdiction(): string
    {
        return $this->jurisdiction;
    }

    // -----------------------------------------------------------------------
    // Issue #751 - embedded image metadata (EXIF / IPTC / XMP) PII scan
    // -----------------------------------------------------------------------

    /**
     * Columns that carry creator / contact / location PII on each of the three
     * sidecar tables populated by ahg-metadata-extraction. Each entry pairs
     * the source column with the canonical pii_type so the persistence layer
     * stays consistent across tables.
     *
     * Jurisdiction-neutral on purpose: GPS coordinates and creator-contact
     * data are PII in every market we ship to. Per-market overlays sit in
     * the privacy_jurisdiction registry, not here.
     *
     * @var array<string,array<string,string>>
     */
    private const EMBEDDED_FIELD_MAP = [
        // digital_object_metadata - generalist extraction sidecar.
        'digital_object_metadata' => [
            'gps_latitude'  => 'gps_coordinate',
            'gps_longitude' => 'gps_coordinate',
            'gps_altitude'  => 'gps_coordinate',
            'creator'       => 'person_name',
            'author'        => 'person_name',
            'artist'        => 'person_name',
            'date_created'  => 'sensitive_date',
        ],
        // dam_iptc_metadata - IPTC IIM + IPTC Core (PhotoMetadata) per-IO.
        'dam_iptc_metadata' => [
            'creator'             => 'person_name',
            'creator_job_title'   => 'person_contact',
            'creator_address'     => 'person_contact',
            'creator_city'        => 'person_contact',
            'creator_state'       => 'person_contact',
            'creator_postal_code' => 'person_contact',
            'creator_country'     => 'person_contact',
            'creator_phone'       => 'person_contact',
            'creator_email'       => 'person_contact',
            'creator_website'     => 'person_contact',
            'date_created'        => 'sensitive_date',
            'broadcast_date'      => 'sensitive_date',
        ],
        // media_metadata - audio / video stream metadata.
        'media_metadata' => [
            'artist'          => 'person_name',
            'gps_coordinates' => 'gps_coordinate',
        ],
    ];

    /**
     * Confidence floor per pii_type. GPS coordinates and explicit contact
     * fields are unambiguous so they ship at 0.95. Names (any string lands
     * in *creator*) get 0.85. Dates without a person attached are weakest at
     * 0.55 - they're still surfaced because EXIF DateTimeOriginal frequently
     * pins a person's presence to a place and time.
     */
    private const EMBEDDED_CONFIDENCE = [
        'gps_coordinate' => 0.95,
        'person_name'    => 0.85,
        'person_contact' => 0.95,
        'sensitive_date' => 0.55,
    ];

    /**
     * Scan embedded image metadata for a digital object. Reads the three
     * sidecar tables populated by MetadataExtractionService and returns a
     * list of PII findings shaped to match scan()'s contract closely enough
     * to share persistence + UI code.
     *
     * Returned shape (one entry per hit):
     *   [
     *     'field'         => 'gps_latitude',
     *     'value'         => '-25.7461',
     *     'pii_type'      => 'gps_coordinate',
     *     'confidence'    => 0.95,
     *     'source_table'  => 'digital_object_metadata',
     *     'source_column' => 'gps_latitude',
     *   ]
     *
     * Empty array is a clean "no PII" signal, NOT a failure - caller should
     * still write a "no findings" audit row if it wants to record the scan
     * happened.
     *
     * @return array<int,array{field:string,value:string,pii_type:string,confidence:float,source_table:string,source_column:string}>
     */
    public function scanEmbeddedMetadata(int $digitalObjectId): array
    {
        if ($digitalObjectId <= 0) {
            return [];
        }

        $findings = [];

        foreach (self::EMBEDDED_FIELD_MAP as $table => $columnMap) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Each sidecar table keys to digital_object_id (or object_id for
            // dam_iptc_metadata, which keys to information_object). For
            // dam_iptc_metadata we additionally check: is this IO linked to
            // the supplied digital_object?  If a caller passes a DO id, we
            // map it back to the IO it points at and pull the per-IO IPTC
            // row.
            try {
                if ($table === 'dam_iptc_metadata') {
                    $row = $this->loadDamIptcForDigitalObject($digitalObjectId);
                } else {
                    $row = DB::table($table)
                        ->where('digital_object_id', $digitalObjectId)
                        ->first();
                }
            } catch (\Throwable $e) {
                // A missing column or read failure on one table should not
                // poison the whole scan. Skip the table and continue.
                continue;
            }

            if (! $row) {
                continue;
            }

            $row = (array) $row;
            foreach ($columnMap as $column => $piiType) {
                $raw = $row[$column] ?? null;
                if ($raw === null) {
                    continue;
                }
                $value = is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_UNESCAPED_SLASHES);
                $value = trim((string) $value);
                if ($value === '' || $value === '0' || $value === '0.00000000') {
                    continue;
                }
                $findings[] = [
                    'field'         => $column,
                    'value'         => $value,
                    'pii_type'      => $piiType,
                    'confidence'    => self::EMBEDDED_CONFIDENCE[$piiType] ?? 0.70,
                    'source_table'  => $table,
                    'source_column' => $column,
                ];
            }
        }

        return $findings;
    }

    /**
     * Persist findings from scanEmbeddedMetadata into ahg_pii_finding_embedded.
     * Returns the count of new rows actually inserted (the UNIQUE on
     * (digital_object_id, pii_type, source_table, source_field) makes
     * re-scans idempotent - duplicate hits are simply ignored).
     *
     * @param array<int,array<string,mixed>> $findings  Output of scanEmbeddedMetadata.
     */
    public function persistEmbeddedFindings(int $digitalObjectId, array $findings): int
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded') || $findings === []) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $inserted = 0;

        foreach ($findings as $f) {
            try {
                $existing = DB::table('ahg_pii_finding_embedded')
                    ->where('digital_object_id', $digitalObjectId)
                    ->where('pii_type',          $f['pii_type'])
                    ->where('source_table',      $f['source_table'])
                    ->where('source_field',      $f['source_column'])
                    ->exists();
                if ($existing) {
                    // Refresh scanned_at so the operator can tell when the
                    // last scan touched this row, but leave resolution_status
                    // alone - a redacted/cleared finding stays resolved.
                    DB::table('ahg_pii_finding_embedded')
                        ->where('digital_object_id', $digitalObjectId)
                        ->where('pii_type',          $f['pii_type'])
                        ->where('source_table',      $f['source_table'])
                        ->where('source_field',      $f['source_column'])
                        ->update([
                            'scanned_at'  => $now,
                            'source_value' => mb_substr((string) $f['value'], 0, 4000),
                            'confidence'  => (float) $f['confidence'],
                        ]);
                    continue;
                }
                DB::table('ahg_pii_finding_embedded')->insert([
                    'digital_object_id' => $digitalObjectId,
                    'pii_type'          => $f['pii_type'],
                    'source_table'      => $f['source_table'],
                    'source_field'      => $f['source_column'],
                    'source_value'      => mb_substr((string) $f['value'], 0, 4000),
                    'confidence'        => (float) $f['confidence'],
                    'resolution_status' => 'pending',
                    'scanned_at'        => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                // Schema not installed yet, or a transient write failure -
                // skip this row and continue. The caller can re-run the
                // backfill command once the schema is up.
                continue;
            }
        }

        return $inserted;
    }

    /**
     * Look up dam_iptc_metadata for a digital_object_id by walking back to
     * the parent information_object. dam_iptc_metadata.object_id stores the
     * IO id, not the DO id.
     */
    private function loadDamIptcForDigitalObject(int $digitalObjectId): ?object
    {
        // digital_object.object_id points back to the parent information_object
        // (CTI: digital_object is a subtype of object). dam_iptc_metadata.object_id
        // is the IO id, so we join through digital_object to find it.
        $objectId = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->value('object_id');
        if (! $objectId) {
            return null;
        }
        return DB::table('dam_iptc_metadata')
            ->where('object_id', $objectId)
            ->first();
    }

    // -----------------------------------------------------------------------
    // Per-type collectors
    // -----------------------------------------------------------------------

    /** @param array<int,array> $findings */
    private function collectEmails(string $text, array &$findings): void
    {
        $this->collectByPattern($text, $findings, 'email', self::BASE_PATTERNS['email']['pattern'], self::BASE_PATTERNS['email']['confidence']);
    }

    /** @param array<int,array> $findings */
    private function collectIps(string $text, array &$findings): void
    {
        $this->collectByPattern($text, $findings, 'ip', self::BASE_PATTERNS['ip']['pattern'], self::BASE_PATTERNS['ip']['confidence']);
        $this->collectByPattern($text, $findings, 'ip', self::BASE_PATTERNS['ip_v6']['pattern'], self::BASE_PATTERNS['ip_v6']['confidence']);
    }

    /** @param array<int,array> $findings */
    private function collectPhones(string $text, array &$findings): void
    {
        $jurisdictions = $this->jurisdictionsToScan('phone');
        foreach ($jurisdictions as $j) {
            if (! isset(self::PHONE_PATTERNS[$j])) {
                continue;
            }
            foreach (self::PHONE_PATTERNS[$j] as $variant) {
                $this->collectByPattern($text, $findings, 'phone', $variant['pattern'], $variant['confidence']);
            }
        }
    }

    /** @param array<int,array> $findings */
    private function collectNationalIds(string $text, array &$findings): void
    {
        $jurisdictions = $this->jurisdictionsToScan('national_id');
        foreach ($jurisdictions as $j) {
            if (! isset(self::NATIONAL_ID_PATTERNS[$j])) {
                continue;
            }
            $cfg = self::NATIONAL_ID_PATTERNS[$j];
            if (! preg_match_all($cfg['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $match) {
                $value = (string) $match[0];
                $confidence = $cfg['confidence'];
                if ($cfg['validator'] !== null && ! $this->{$cfg['validator']}($value)) {
                    // Pattern matched but checksum failed: lower confidence
                    // rather than discard - useful signal during review.
                    $confidence = max(0.3, $confidence - 0.4);
                } elseif ($cfg['validator'] !== null) {
                    $confidence = min(0.95, $confidence + 0.2);
                }
                $offset = (int) $match[1];
                $findings[] = [
                    'type'         => 'national_id',
                    'value'        => $value,
                    'offset_start' => $offset,
                    'offset_end'   => $offset + strlen($value),
                    'confidence'   => $confidence,
                ];
            }
        }
    }

    /** @param array<int,array> $findings */
    private function collectCreditCards(string $text, array &$findings): void
    {
        if (! preg_match_all(self::BASE_PATTERNS['credit_card']['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($matches[0] as $match) {
            $value = (string) $match[0];
            $digits = preg_replace('/\D/', '', $value);
            if ($digits === null || strlen($digits) < 13 || strlen($digits) > 19) {
                continue;
            }
            if (! $this->luhn($digits)) {
                continue; // Discard non-Luhn candidates - too noisy otherwise.
            }
            $offset = (int) $match[1];
            $findings[] = [
                'type'         => 'credit_card',
                'value'        => $value,
                'offset_start' => $offset,
                'offset_end'   => $offset + strlen($value),
                'confidence'   => 0.95,
            ];
        }
    }

    /** @param array<int,array> $findings */
    private function collectDatesOfBirth(string $text, array &$findings): void
    {
        if (! preg_match_all(self::BASE_PATTERNS['date_of_birth']['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $currentYear = (int) date('Y');
        foreach ($matches[0] as $match) {
            $value = (string) $match[0];
            // Sanity-bound the year so 1066 / 3017 don't match. ISO and DMY both
            // place the 4-digit year either at the start or the end of the value.
            if (preg_match('/(?:^|\D)(\d{4})(?:$|\D)/', $value, $year)) {
                $y = (int) $year[1];
                if ($y < 1900 || $y > $currentYear) {
                    continue;
                }
            }
            $offset = (int) $match[1];
            $findings[] = [
                'type'         => 'date_of_birth',
                'value'        => $value,
                'offset_start' => $offset,
                'offset_end'   => $offset + strlen($value),
                'confidence'   => self::BASE_PATTERNS['date_of_birth']['confidence'],
            ];
        }
    }

    /** @param array<int,array> $findings */
    private function collectByPattern(string $text, array &$findings, string $type, string $pattern, float $confidence): void
    {
        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($matches[0] as $match) {
            $value = (string) $match[0];
            $offset = (int) $match[1];
            $findings[] = [
                'type'         => $type,
                'value'        => $value,
                'offset_start' => $offset,
                'offset_end'   => $offset + strlen($value),
                'confidence'   => $confidence,
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Validators
    // -----------------------------------------------------------------------

    private function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }
        return $sum % 10 === 0;
    }

    /**
     * SA ID number checksum (Luhn-like). Reject obvious sentinels (all zeros).
     */
    private function validateSaIdNumber(string $value): bool
    {
        if (! preg_match('/^\d{13}$/', $value) || $value === str_repeat('0', 13)) {
            return false;
        }
        return $this->luhn($value);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Decide which jurisdictions' regex sets to apply. When the configured
     * jurisdiction is gdpr (member-state-agnostic) or empty, we scan the union
     * of POPIA, UK GDPR and CCPA patterns to maximise recall on free-text
     * content uploaded from any market.
     *
     * @return string[]
     */
    private function jurisdictionsToScan(string $type): array
    {
        if ($this->jurisdiction === 'gdpr' || $this->jurisdiction === '') {
            return ['gdpr', 'popia', 'uk_gdpr', 'ccpa'];
        }
        return [$this->jurisdiction];
    }

    /**
     * Drop overlapping duplicate spans. Two cases:
     *
     *  1) Same type, fully overlapping (e.g. e164 + local phone regex both
     *     matching the same number): keep the higher-confidence entry.
     *  2) Cross-type collision where one detector is more authoritative than
     *     another (national_id beats credit_card, credit_card beats phone):
     *     drop the lower-priority finding when its span is fully contained in
     *     the higher-priority finding's span. This stops a 13-digit SA ID
     *     from also appearing as a Luhn-passing credit card, and stops the
     *     trailing digits of a credit card from being reported as a phone.
     *
     * @param array<int,array> $findings
     * @return array<int,array>
     */
    private function dedupeOverlaps(array $findings): array
    {
        // Type priority (high -> low). Higher index wins overlap arbitration.
        $priority = [
            'phone'         => 1,
            'credit_card'   => 2,
            'national_id'   => 3,
            'email'         => 4,
            'ip'            => 4,
            'date_of_birth' => 0,
        ];

        // Sort by priority desc so when we walk in order, dominant findings
        // arrive first and lower-priority overlaps are suppressed.
        usort($findings, static function (array $a, array $b) use ($priority): int {
            $pa = $priority[$a['type']] ?? 0;
            $pb = $priority[$b['type']] ?? 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return $a['offset_start'] <=> $b['offset_start'];
        });

        $accepted = [];
        foreach ($findings as $f) {
            $dup = false;
            foreach ($accepted as $existing) {
                $overlaps = !($f['offset_end'] <= $existing['offset_start']
                    || $f['offset_start'] >= $existing['offset_end']);
                if (! $overlaps) {
                    continue;
                }
                // Same type: always drop the second occurrence (we kept the
                // higher-priority/earlier one).
                if ($existing['type'] === $f['type']) {
                    $dup = true;
                    break;
                }
                // Cross-type: drop only when fully contained in the dominant
                // span. Partial overlaps (rare) stay so the reviewer can see
                // both signals.
                $contained = $f['offset_start'] >= $existing['offset_start']
                    && $f['offset_end'] <= $existing['offset_end'];
                if ($contained) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                $accepted[] = $f;
            }
        }

        // Restore offset-ascending order for the caller.
        usort($accepted, static function (array $a, array $b): int {
            if ($a['offset_start'] === $b['offset_start']) {
                return $b['confidence'] <=> $a['confidence'];
            }
            return $a['offset_start'] <=> $b['offset_start'];
        });

        return $accepted;
    }

    private function resolveJurisdiction(): string
    {
        try {
            if (! Schema::hasTable('ahg_setting')) {
                return 'gdpr';
            }
            $row = DB::table('ahg_setting')->where('key', 'privacy_jurisdiction')->first(['value']);
            $value = $row->value ?? null;
            if (is_string($value) && $value !== '') {
                return strtolower($value);
            }
        } catch (\Throwable $e) {
            // ignore - fall through to default
        }
        return 'gdpr';
    }
}
