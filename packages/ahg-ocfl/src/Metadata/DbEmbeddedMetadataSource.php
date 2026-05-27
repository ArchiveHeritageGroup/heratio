<?php

/**
 * DbEmbeddedMetadataSource - resolve embedded image metadata from the
 * canonical sidecar storage used by ahg-metadata-extraction.
 *
 * Heratio stores extracted EXIF / IPTC / XMP / GPS in two places:
 *
 *   1. `property` (with scope='metadata_extraction') + `property_i18n` -
 *      the catch-all key/value sink populated by
 *      MetadataExtractionService::extractFromDigitalObject(). Keys carry
 *      a "section:field" prefix (e.g. "exif:Make", "iptc:byline",
 *      "xmp:title") that lets us group them into the three OCFL extension
 *      blocks without re-extracting from the file.
 *
 *   2. `dam_iptc_metadata` - the typed mirror used by the DAM module. Used
 *      as an IPTC fallback when (1) is empty: a DAM-only ingest path will
 *      have populated dam_iptc_metadata without going through the property
 *      table writer.
 *
 * The mapping from OCFL object id back to the originating
 * information_object id uses `ahg_ocfl_object_map`, which the
 * OcflIngestCommand upserts on every ingest. Without that map we cannot
 * resolve - fail soft with an empty result.
 *
 * Implementations MUST never throw out of fetch(); the OCFL ingest must
 * survive an extraction-side outage.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Metadata;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DbEmbeddedMetadataSource implements EmbeddedMetadataSource
{
    /**
     * The three block prefixes the property-table writer emits. Keys with
     * any other prefix (file:*, exiftool:*, pdf:*, gps:*) are intentionally
     * excluded: the extension covers ONLY the OCFL-spec-aligned EXIF / IPTC
     * / XMP slots. Container-level technical metadata belongs to a future
     * `ahg-technical-metadata` extension if we ever ship one.
     */
    private const BLOCK_PREFIXES = ['exif', 'iptc', 'xmp'];

    public function fetch(string $ocflObjectId): array
    {
        try {
            $ioId = $this->resolveIoId($ocflObjectId);
            if ($ioId === null) {
                return [];
            }

            $byBlock = $this->fromPropertyTable($ioId);

            // IPTC fallback from dam_iptc_metadata when property-table iptc
            // is empty. DAM-direct uploads bypass the property writer.
            if (empty($byBlock['iptc'])) {
                $iptc = $this->fromDamIptcTable($ioId);
                if ($iptc !== []) {
                    $byBlock['iptc'] = $iptc;
                }
            }

            return array_filter($byBlock, fn ($b) => is_array($b) && $b !== []);
        } catch (\Throwable $e) {
            // Never break OCFL ingest because of metadata-side issues.
            Log::warning(
                'ahg-ocfl DbEmbeddedMetadataSource: fetch failed for '
                .$ocflObjectId.' - '.$e->getMessage()
            );
            return [];
        }
    }

    /**
     * Map an OCFL object id back to information_object.id via the
     * upsert table maintained by OcflIngestCommand.
     */
    private function resolveIoId(string $ocflObjectId): ?int
    {
        $row = DB::table('ahg_ocfl_object_map')
            ->where('ocfl_object_id', $ocflObjectId)
            ->first();
        if ($row && isset($row->information_object_id)) {
            return (int) $row->information_object_id;
        }

        // Fallback: parse the canonical urn:heratio:io:{id} form. Useful
        // for the backfill command when the map row hasn't been written
        // yet (or was lost), and for tests that don't seed the map.
        if (preg_match('/^urn:heratio:io:(\d+)$/i', $ocflObjectId, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Pull all metadata_extraction properties for the IO, group by the
     * "section:" prefix on the property name, drop the prefix in the
     * output. Keys are decoded once (JSON list values were stored as
     * JSON strings by MetadataExtractionService::flattenMetadata).
     *
     * @return array{exif?: array<string,mixed>, iptc?: array<string,mixed>, xmp?: array<string,mixed>}
     */
    private function fromPropertyTable(int $ioId): array
    {
        // Pull DOs attached to this IO, then their metadata_extraction
        // property rows. DOs are children of the IO via object_id.
        $doIds = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->pluck('id')
            ->all();
        if ($doIds === []) {
            return [];
        }

        $rows = DB::table('property as p')
            ->join('property_i18n as pi', 'pi.id', '=', 'p.id')
            ->whereIn('p.object_id', $doIds)
            ->where('p.scope', 'metadata_extraction')
            ->select('p.name', 'pi.value')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $name = (string) $r->name;
            $pos  = strpos($name, ':');
            if ($pos === false || $pos === 0) {
                continue;
            }
            $section = strtolower(substr($name, 0, $pos));
            if (! in_array($section, self::BLOCK_PREFIXES, true)) {
                continue;
            }
            $key  = substr($name, $pos + 1);
            $val  = $this->maybeDecodeJsonList((string) ($r->value ?? ''));
            $out[$section][$key] = $val;
        }
        return $out;
    }

    /**
     * The DAM typed mirror. We only emit the IPTC-shaped subset of columns
     * (creator/byline, caption, copyright, keywords, location, dates) -
     * the camera-tech / image-dimension columns belong to EXIF and would
     * pollute the IPTC block.
     *
     * @return array<string,mixed>
     */
    private function fromDamIptcTable(int $ioId): array
    {
        if (! DB::getSchemaBuilder()->hasTable('dam_iptc_metadata')) {
            return [];
        }
        // dam_iptc_metadata.object_id references digital_object.id, not
        // information_object.id. Walk through digital_object.
        $row = DB::table('dam_iptc_metadata as d')
            ->join('digital_object as dobj', 'dobj.id', '=', 'd.object_id')
            ->where('dobj.object_id', $ioId)
            ->orderBy('d.id')
            ->first();
        if (! $row) {
            return [];
        }

        $cols = [
            'creator'         => 'creator',
            'creator_title'   => 'creator_title',
            'byline_title'    => 'byline_title',
            'caption'         => 'caption',
            'caption_writer'  => 'caption_writer',
            'headline'        => 'headline',
            'keywords'        => 'keywords',
            'date_created'    => 'date_created',
            'copyright_notice'=> 'copyright_notice',
            'credit_line'     => 'credit_line',
            'source'          => 'source',
            'city'            => 'city',
            'state_province'  => 'state_province',
            'country'         => 'country',
        ];
        $out = [];
        foreach ($cols as $col => $outKey) {
            $v = $row->{$col} ?? null;
            if ($v !== null && $v !== '') {
                $out[$outKey] = $v;
            }
        }
        return $out;
    }

    /**
     * MetadataExtractionService::flattenMetadata json_encode()s sequential
     * list values (XMP creators, IPTC multi-keywords). Decode them back so
     * the OCFL extension carries the structured shape (preservation value
     * is in the structure, not in a stringified array).
     */
    private function maybeDecodeJsonList(string $value): mixed
    {
        $trim = ltrim($value);
        if ($trim === '' || $trim[0] !== '[') {
            return $value;
        }
        try {
            $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : $value;
        } catch (\Throwable) {
            // Malformed JSON - keep raw so the operator can still see it.
            return $value;
        }
    }
}
