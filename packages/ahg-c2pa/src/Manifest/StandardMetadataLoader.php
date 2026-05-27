<?php
/**
 * Heratio - load embedded image/media metadata from Heratio sidecar tables
 * (digital_object_metadata, dam_iptc_metadata, media_metadata) and project
 * it into the three C2PA 2.1 "Standard Metadata Assertions": stds.exif,
 * stds.iptc, stds.xmp.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Manifest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * StandardMetadataLoader reads a digital object's sidecar metadata rows
 * and returns three normalised arrays ready to drop into the C2PA stds.*
 * assertions.
 *
 * Spec reference - C2PA 2.1 § Standard Metadata Assertions:
 *
 *   - stds.exif : key-value map using Exif standard tag names
 *                 (Exif/DateTimeOriginal, Exif/Make, Exif/Model,
 *                 Exif/GPSLatitude, Exif/GPSLongitude, Exif/ImageWidth,
 *                 Exif/ImageHeight, Exif/Software, Exif/Artist,
 *                 Exif/Copyright, Exif/ImageDescription).
 *
 *   - stds.iptc : key-value map using IPTC IIM / Photo Metadata
 *                 names (By-line, Copyright, Headline, Keywords,
 *                 Caption-Abstract, City, Country-PrimaryLocationName,
 *                 Credit, Source, ObjectName).
 *
 *   - stds.xmp  : key-value map using Dublin Core / XMP names
 *                 (dc:creator, dc:rights, dc:title, dc:subject,
 *                 dc:description, dc:date, xmpRights:Marked, xmpRights:UsageTerms).
 *
 * Each loader method is independent and will gracefully return [] if:
 *   - the sidecar table does not exist (fresh install pre-bootstrap)
 *   - the row for the digital object id is missing
 *   - the row is present but every relevant column is null/empty
 *
 * The loader never throws on missing/garbled data. It is the manifest
 * builder's job to decide whether to emit an assertion when the array
 * returned is empty (it doesn't).
 */
final class StandardMetadataLoader
{
    /**
     * Build a stds.exif payload from digital_object_metadata + media_metadata.
     *
     * The digital_object_metadata row is preferred (it stores image-flavour
     * EXIF). Falls back to media_metadata for video/audio assets that have
     * camera-make / model + duration but no image dims.
     *
     * PII gate (issue #751): if `ahg_pii_finding_embedded` has any row for
     * this digital object with `pii_type='gps_coordinate'` and
     * `resolution_status IN ('pending','escalated')`, the GPS keys are
     * stripped and a `_pii_redacted: true` marker is set on the payload so
     * downstream verifiers can see the redaction was intentional. The gate
     * is defensive - if the table is absent (Phase 2 not yet shipped on
     * this install) we proceed without redaction and log a debug warning.
     *
     * @return array<string,scalar|bool> empty when no usable data was found.
     */
    public function loadExif(int $digitalObjectId): array
    {
        $row = $this->fetchRow('digital_object_metadata', 'digital_object_id', $digitalObjectId);
        $media = $this->fetchRow('media_metadata', 'digital_object_id', $digitalObjectId);

        $out = [];

        // digital_object_metadata - the canonical EXIF source for image assets.
        if ($row !== null) {
            $this->copyIfSet($out, 'Exif/DateTimeOriginal', $row, 'date_created');
            $this->copyIfSet($out, 'Exif/Make',             $row, 'camera_make');
            $this->copyIfSet($out, 'Exif/Model',            $row, 'camera_model');
            $this->copyIfSet($out, 'Exif/ImageWidth',       $row, 'image_width');
            $this->copyIfSet($out, 'Exif/ImageHeight',      $row, 'image_height');
            $this->copyIfSet($out, 'Exif/Artist',           $row, 'creator');
            $this->copyIfSet($out, 'Exif/Copyright',        $row, 'copyright');
            $this->copyIfSet($out, 'Exif/ImageDescription', $row, 'description');

            $lat = $row['gps_latitude']  ?? null;
            $lon = $row['gps_longitude'] ?? null;
            if (self::numeric($lat)) {
                $out['Exif/GPSLatitude']  = (float) $lat;
                $out['Exif/GPSLatitudeRef'] = ((float) $lat) >= 0 ? 'N' : 'S';
            }
            if (self::numeric($lon)) {
                $out['Exif/GPSLongitude']  = (float) $lon;
                $out['Exif/GPSLongitudeRef'] = ((float) $lon) >= 0 ? 'E' : 'W';
            }
        }

        // media_metadata - fill the gaps for video/audio (no image dims, but
        // we can still surface device + software fields).
        if ($media !== null) {
            $this->copyIfSet($out, 'Exif/Make',     $media, 'make');
            $this->copyIfSet($out, 'Exif/Model',    $media, 'model');
            $this->copyIfSet($out, 'Exif/Software', $media, 'software');
            // duration is non-EXIF strictly speaking, but many C2PA
            // verifiers surface video duration via Exif/Duration.
            if (!isset($out['Exif/Duration']) && isset($media['duration']) && self::numeric($media['duration'])) {
                $out['Exif/Duration'] = (float) $media['duration'];
            }
        }

        if ($this->hasPendingGpsFinding($digitalObjectId)) {
            $hadGps = isset($out['Exif/GPSLatitude']) || isset($out['Exif/GPSLongitude']);
            unset(
                $out['Exif/GPSLatitude'],
                $out['Exif/GPSLatitudeRef'],
                $out['Exif/GPSLongitude'],
                $out['Exif/GPSLongitudeRef'],
            );
            if ($hadGps) {
                $out['_pii_redacted'] = true;
            }
        }

        return $out;
    }

    /**
     * Returns true when ahg_pii_finding_embedded has a pending/escalated
     * gps_coordinate finding for the digital object. False on any error or
     * when the table is missing - the gate fails open so a fresh install
     * pre-#751 doesn't break existing manifest issuance, but a warning is
     * logged so the operator can spot the gap.
     */
    private function hasPendingGpsFinding(int $digitalObjectId): bool
    {
        if (!$this->tableExists('ahg_pii_finding_embedded')) {
            // Defensive fail-open. Log once at debug-level - in production
            // this means Phase 2 of #751 has not been deployed yet.
            try {
                Log::debug('c2pa.stds_exif: PII gate table absent, GPS not redacted', [
                    'digital_object_id' => $digitalObjectId,
                ]);
            } catch (Throwable) {
                // Log facade not bound - test harness without a logger.
            }
            return false;
        }
        try {
            return DB::table('ahg_pii_finding_embedded')
                ->where('digital_object_id', $digitalObjectId)
                ->where('pii_type', 'gps_coordinate')
                ->whereIn('resolution_status', ['pending', 'escalated'])
                ->exists();
        } catch (Throwable $e) {
            try {
                Log::warning('c2pa.stds_exif: PII gate query failed; proceeding without redaction', [
                    'digital_object_id' => $digitalObjectId,
                    'err'               => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // ignore - test harness
            }
            return false;
        }
    }

    /**
     * Build a stds.iptc payload from dam_iptc_metadata.
     *
     * dam_iptc_metadata is keyed by object_id, not digital_object_id - we
     * accept the digital_object_id and the caller-supplied parent object_id
     * (usually information_object.id) and try both columns. If neither finds
     * a row we return [].
     *
     * @return array<string,scalar>
     */
    public function loadIptc(int $digitalObjectId, ?int $objectId = null): array
    {
        $row = null;

        if ($this->tableExists('dam_iptc_metadata')) {
            try {
                if ($objectId !== null) {
                    $row = (array) DB::table('dam_iptc_metadata')
                        ->where('object_id', $objectId)
                        ->first();
                }
                if (!$row) {
                    // Some installs use the digital object id directly.
                    $row = (array) DB::table('dam_iptc_metadata')
                        ->where('object_id', $digitalObjectId)
                        ->first();
                }
            } catch (Throwable) {
                $row = null;
            }
        }

        if (!$row) {
            return [];
        }

        $out = [];
        $this->copyIfSet($out, 'By-line',                        $row, 'creator');
        $this->copyIfSet($out, 'By-lineTitle',                   $row, 'creator_job_title');
        $this->copyIfSet($out, 'CopyrightNotice',                $row, 'copyright_notice');
        $this->copyIfSet($out, 'Headline',                       $row, 'headline');
        $this->copyIfSet($out, 'Caption-Abstract',               $row, 'caption');
        $this->copyIfSet($out, 'ObjectName',                     $row, 'title');
        $this->copyIfSet($out, 'City',                           $row, 'city');
        $this->copyIfSet($out, 'Province-State',                 $row, 'state_province');
        $this->copyIfSet($out, 'Country-PrimaryLocationName',    $row, 'country');
        $this->copyIfSet($out, 'Country-PrimaryLocationCode',    $row, 'country_code');
        $this->copyIfSet($out, 'Sub-location',                   $row, 'sublocation');
        $this->copyIfSet($out, 'Credit',                         $row, 'credit_line');
        $this->copyIfSet($out, 'Source',                         $row, 'source');
        $this->copyIfSet($out, 'SpecialInstructions',            $row, 'instructions');
        $this->copyIfSet($out, 'IntellectualGenre',              $row, 'intellectual_genre');
        $this->copyIfSet($out, 'SubjectReference',               $row, 'iptc_subject_code');
        $this->copyIfSet($out, 'Scene',                          $row, 'iptc_scene');
        $this->copyIfSet($out, 'DateCreated',                    $row, 'date_created');

        // Keywords is a multi-valued field per IPTC IIM. We accept comma
        // or semicolon separated values and emit a list.
        if (isset($row['keywords']) && is_string($row['keywords']) && trim($row['keywords']) !== '') {
            $parts = preg_split('/[,;]\s*/', (string) $row['keywords']) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
            if ($parts !== []) {
                $out['Keywords'] = $parts;
            }
        }

        return $out;
    }

    /**
     * Build a stds.xmp payload (Dublin Core + xmpRights subset) from the
     * same underlying rows. XMP is the "modern" face of EXIF+IPTC, so we
     * derive it from what we already loaded rather than from a separate
     * sidecar.
     *
     * @return array<string,mixed>
     */
    public function loadXmp(int $digitalObjectId, ?int $objectId = null): array
    {
        $row = $this->fetchRow('digital_object_metadata', 'digital_object_id', $digitalObjectId);
        $iptc = null;
        if ($this->tableExists('dam_iptc_metadata')) {
            try {
                if ($objectId !== null) {
                    $iptc = (array) DB::table('dam_iptc_metadata')->where('object_id', $objectId)->first();
                }
                if (!$iptc) {
                    $iptc = (array) DB::table('dam_iptc_metadata')->where('object_id', $digitalObjectId)->first();
                }
            } catch (Throwable) {
                $iptc = null;
            }
        }

        $out = [];

        // dc:creator   - prefer IPTC By-line, fall back to digital_object_metadata.creator
        $creator = $iptc['creator'] ?? null;
        if (!self::nonEmptyString($creator) && $row !== null) {
            $creator = $row['creator'] ?? null;
        }
        if (self::nonEmptyString($creator)) {
            $out['dc:creator'] = [trim((string) $creator)];
        }

        // dc:rights    - prefer IPTC copyright_notice, fall back to digital_object_metadata.copyright
        $rights = $iptc['copyright_notice'] ?? null;
        if (!self::nonEmptyString($rights) && $row !== null) {
            $rights = $row['copyright'] ?? null;
        }
        if (self::nonEmptyString($rights)) {
            $out['dc:rights'] = ['x-default' => trim((string) $rights)];
        }

        // dc:title     - prefer IPTC title, fall back to digital_object_metadata.title
        $title = $iptc['title'] ?? null;
        if (!self::nonEmptyString($title) && $row !== null) {
            $title = $row['title'] ?? null;
        }
        if (self::nonEmptyString($title)) {
            $out['dc:title'] = ['x-default' => trim((string) $title)];
        }

        // dc:description
        if ($row !== null && self::nonEmptyString($row['description'] ?? null)) {
            $out['dc:description'] = ['x-default' => trim((string) $row['description'])];
        } elseif ($iptc !== null && self::nonEmptyString($iptc['caption'] ?? null)) {
            $out['dc:description'] = ['x-default' => trim((string) $iptc['caption'])];
        }

        // dc:subject (keywords)
        $keywordsSrc = null;
        if ($iptc !== null && self::nonEmptyString($iptc['keywords'] ?? null)) {
            $keywordsSrc = $iptc['keywords'];
        } elseif ($row !== null && self::nonEmptyString($row['keywords'] ?? null)) {
            $keywordsSrc = $row['keywords'];
        }
        if ($keywordsSrc !== null) {
            $parts = preg_split('/[,;]\s*/', (string) $keywordsSrc) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
            if ($parts !== []) {
                $out['dc:subject'] = $parts;
            }
        }

        // dc:date - prefer IPTC DateCreated, fall back to digital_object_metadata.date_created
        $date = $iptc['date_created'] ?? null;
        if (!self::nonEmptyString($date) && $row !== null) {
            $date = $row['date_created'] ?? null;
        }
        if (self::nonEmptyString($date)) {
            $out['dc:date'] = [trim((string) $date)];
        }

        // xmpRights:Marked + UsageTerms
        if ($iptc !== null) {
            if (self::nonEmptyString($iptc['copyright_notice'] ?? null)) {
                $out['xmpRights:Marked'] = true;
            }
            if (self::nonEmptyString($iptc['rights_usage_terms'] ?? null)) {
                $out['xmpRights:UsageTerms'] = ['x-default' => trim((string) $iptc['rights_usage_terms'])];
            }
        }

        return $out;
    }

    /**
     * Convenience: load all three and return a list of Assertion objects,
     * skipping any that came back empty. ManifestBuilder uses this.
     *
     * @return list<Assertion>
     */
    public function loadAssertions(int $digitalObjectId, ?int $objectId = null): array
    {
        $out = [];
        $exif = $this->loadExif($digitalObjectId);
        $iptc = $this->loadIptc($digitalObjectId, $objectId);
        $xmp  = $this->loadXmp($digitalObjectId, $objectId);
        if ($exif !== []) {
            $out[] = Assertion::stdsExif($exif);
        }
        if ($iptc !== []) {
            $out[] = Assertion::stdsIptc($iptc);
        }
        if ($xmp !== []) {
            $out[] = Assertion::stdsXmp($xmp);
        }
        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchRow(string $table, string $column, int $value): ?array
    {
        if (!$this->tableExists($table)) {
            return null;
        }
        try {
            $row = DB::table($table)->where($column, $value)->first();
        } catch (Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        return (array) $row;
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Copy $row[$srcKey] to $out[$dstKey] if the source is non-empty.
     *
     * @param array<string,scalar|null> $out
     * @param array<string,mixed> $row
     */
    private function copyIfSet(array &$out, string $dstKey, array $row, string $srcKey): void
    {
        $v = $row[$srcKey] ?? null;
        if ($v === null || $v === '') {
            return;
        }
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return;
            }
        }
        if (is_scalar($v)) {
            $out[$dstKey] = $v;
        }
    }

    private static function nonEmptyString(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '';
    }

    private static function numeric(mixed $v): bool
    {
        return is_numeric($v) && (string) $v !== '';
    }
}
