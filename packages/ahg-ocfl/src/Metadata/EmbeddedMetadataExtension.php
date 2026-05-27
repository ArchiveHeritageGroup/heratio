<?php

/**
 * EmbeddedMetadataExtension - shape + validation for the
 * `ahg-embedded-metadata` OCFL v1.1 extension block.
 *
 * Per OCFL v1.1 §3.7 (Object Extensions), an extension is a vendor-defined
 * addition to the inventory.json that does not affect spec-mandated fields
 * (id / type / digestAlgorithm / head / manifest / versions / fixity). The
 * inventory carries an optional `extensions` object whose keys are extension
 * names following the OCFL Extension naming convention. The shape under each
 * key is defined by the extension owner.
 *
 * The `ahg-embedded-metadata` extension carries intrinsic content metadata
 * extracted from the source digital objects at version-creation time so the
 * inventory documents what the object *was* at that point, not merely its
 * bytes. This is preservation-significant context (camera Make/Model,
 * IPTC byline, XMP rights) that must survive format migration and outlive
 * the originating SQL row.
 *
 * Block shape (all three sub-blocks optional; missing == not extracted):
 *
 *   {
 *     "ahg-embedded-metadata": {
 *       "exif":              { "Make": "Nikon", "Model": "D850", ... },
 *       "iptc":              { "byline": "J. Pieterse", ... },
 *       "xmp":               { "title": "...", "rights": "..." },
 *       "captured_at":       "2026-05-27T08:00:00+02:00",  // RFC 3339
 *       "extractor_version": "ahg-metadata-extraction@1.0"
 *     }
 *   }
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Metadata;

use DateTimeImmutable;
use DateTimeInterface;

final class EmbeddedMetadataExtension
{
    public const NAME    = 'ahg-embedded-metadata';
    public const VERSION = '0.1';

    /**
     * Build a deterministic, sortable extension array from raw data.
     *
     * The caller passes whichever blocks were found; missing blocks are
     * dropped. If ALL three blocks are missing the method returns null
     * so the caller can decide whether to emit an empty marker or omit
     * the extension entirely (the OCFL ingest path uses null == omit).
     *
     * Keys inside each block are alpha-sorted to keep two equivalent
     * extracts byte-identical in the inventory.json (matches the
     * deterministic-encoding guarantee already in Inventory::toJson).
     */
    public static function build(
        array $raw,
        ?string $extractorVersion = null,
        ?DateTimeInterface $capturedAt = null,
    ): ?array {
        $exif = self::cleanBlock($raw['exif'] ?? []);
        $iptc = self::cleanBlock($raw['iptc'] ?? []);
        $xmp  = self::cleanBlock($raw['xmp']  ?? []);

        if ($exif === null && $iptc === null && $xmp === null) {
            return null;
        }

        $out = [];
        if ($exif !== null) {
            $out['exif'] = $exif;
        }
        if ($iptc !== null) {
            $out['iptc'] = $iptc;
        }
        if ($xmp !== null) {
            $out['xmp'] = $xmp;
        }

        $out['captured_at'] = ($capturedAt ?? new DateTimeImmutable('now'))
            ->format(DateTimeInterface::RFC3339);
        $out['extractor_version'] = $extractorVersion
            ?? ('ahg-metadata-extraction@'.self::VERSION);

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Trim a block: drop nulls / empty strings, alpha-sort by key, and
     * coerce stringy values to strings. Returns null when the block has
     * nothing useful left so the caller can omit it from the extension.
     */
    private static function cleanBlock(mixed $block): ?array
    {
        if (! is_array($block) || $block === []) {
            return null;
        }
        $out = [];
        foreach ($block as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $out[(string) $k] = self::coerceValue($v);
        }
        if ($out === []) {
            return null;
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Best-effort scalar coercion. Arrays survive (XMP creators list, IPTC
     * multi-keywords). Anything else gets cast through (string).
     */
    private static function coerceValue(mixed $v): mixed
    {
        if (is_array($v)) {
            // Recurse + drop empties, preserve numeric vs assoc shape.
            $cleaned = [];
            foreach ($v as $k => $vv) {
                if ($vv === null || $vv === '') {
                    continue;
                }
                $cleaned[$k] = self::coerceValue($vv);
            }
            return $cleaned;
        }
        if (is_scalar($v)) {
            return is_string($v) ? trim($v) : $v;
        }
        return (string) $v;
    }
}
