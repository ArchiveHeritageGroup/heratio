<?php

/**
 * EmbeddedMetadataSource - resolver contract for the `ahg-embedded-metadata`
 * OCFL v1.1 extension.
 *
 * The OCFL ingest pipeline needs to know, for a given OCFL object id, what
 * EXIF / IPTC / XMP metadata is currently associated with the source digital
 * objects (and was extracted by ahg-metadata-extraction into property /
 * dam_iptc_metadata sidecar storage). This interface keeps that lookup
 * pluggable so the OCFL package can be exercised without a live database
 * (the in-memory fixture in tests/Unit/InventoryEmbeddedMetadataTest.php
 * implements it directly).
 *
 * Returned shape (any of the three blocks may be omitted; if none are
 * present the caller should NOT emit the extension at all):
 *
 *   [
 *     'exif'  => ['Make' => 'Nikon', ...],          // optional
 *     'iptc'  => ['byline' => 'J. Pieterse', ...],  // optional
 *     'xmp'   => ['title' => '...', ...],           // optional
 *   ]
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Metadata;

interface EmbeddedMetadataSource
{
    /**
     * Resolve embedded image metadata for an OCFL object id.
     *
     * The OCFL object id is namespaced (e.g. `urn:heratio:io:42`); the
     * implementation translates that back to the originating row.
     *
     * Implementations MUST gracefully degrade on missing rows / invalid
     * JSON / unexpected types - never throw out of this method. Returning
     * an empty array means "no embedded metadata available" and is a
     * valid result (the extension block will then be omitted entirely).
     *
     * @return array{exif?: array, iptc?: array, xmp?: array}
     */
    public function fetch(string $ocflObjectId): array;
}
