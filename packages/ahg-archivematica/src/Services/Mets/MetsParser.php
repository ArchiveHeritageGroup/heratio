<?php

/**
 * MetsParser - parse an Archivematica DIP METS.xml into a structured array.
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

namespace AhgArchivematica\Services\Mets;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Parses a DIP `METS.xml` (as produced by Archivematica) and returns a plain,
 * framework-free structured array:
 *
 *   [
 *     'objid'       => '<AIP UUID from mets/@OBJID>' | null,
 *     'dublin_core' => ['title' => ..., 'identifier' => ..., ...],  // Dublin Core
 *     'access_files'=> [                                            // fileSec USE=access
 *        ['file_id'=>, 'use'=>'access', 'href'=>, 'mimetype'=>,
 *         'checksum'=>, 'checksum_type'=>, 'size'=>, 'admid'=>],
 *        ...
 *     ],
 *     'premis'      => [                                            // PREMIS objects
 *        ['object_identifier'=>, 'original_name'=>, 'message_digest'=>,
 *         'message_digest_algorithm'=>, 'size'=>, 'format_name'=>, 'puid'=>,
 *         'admid'=>],
 *        ...
 *     ],
 *   ]
 *
 * Deliberately uses only DOMDocument/DOMXPath (no Laravel facades) so it is
 * cheap to unit-test against a fixture.
 */
class MetsParser
{
    public const NS_METS  = 'http://www.loc.gov/METS/';
    public const NS_XLINK = 'http://www.w3.org/1999/xlink';
    public const NS_DC    = 'http://purl.org/dc/elements/1.1/';
    public const NS_DCTERMS = 'http://purl.org/dc/terms/';
    // Archivematica emits PREMIS v3; older AIPs used v2. Register both.
    public const NS_PREMIS_V3 = 'http://www.loc.gov/premis/v3';
    public const NS_PREMIS_V2 = 'info:lc/xmlns/premis-v2';

    /**
     * Parse a METS file from disk.
     *
     * @return array<string,mixed>
     *
     * @throws RuntimeException when the file is missing or not valid XML.
     */
    public function parseFile(string $metsPath): array
    {
        if (! is_file($metsPath)) {
            throw new RuntimeException("METS file not found: {$metsPath}");
        }
        $xml = file_get_contents($metsPath);
        if ($xml === false) {
            throw new RuntimeException("Cannot read METS file: {$metsPath}");
        }

        return $this->parseString($xml);
    }

    /**
     * Parse a METS document from a string.
     *
     * @return array<string,mixed>
     *
     * @throws RuntimeException when the string is not valid XML.
     */
    public function parseString(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument();
        $loaded = $doc->loadXML($xml);
        if (! $loaded) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $first = $errors[0]->message ?? 'unknown parse error';
            throw new RuntimeException('Invalid METS XML: ' . trim($first));
        }
        libxml_use_internal_errors($prev);

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('mets', self::NS_METS);
        $xp->registerNamespace('xlink', self::NS_XLINK);
        $xp->registerNamespace('dc', self::NS_DC);
        $xp->registerNamespace('dcterms', self::NS_DCTERMS);
        $xp->registerNamespace('premis', self::NS_PREMIS_V3);
        $xp->registerNamespace('premis2', self::NS_PREMIS_V2);

        return [
            'objid'        => $this->extractObjid($xp),
            'dublin_core'  => $this->extractDublinCore($xp),
            'access_files' => $this->extractAccessFiles($xp),
            'premis'       => $this->extractPremisObjects($xp),
        ];
    }

    /**
     * mets/@OBJID - Archivematica sets this to the AIP UUID.
     */
    private function extractObjid(DOMXPath $xp): ?string
    {
        $node = $xp->query('/mets:mets/@OBJID')->item(0);
        $val = $node ? trim($node->nodeValue) : '';

        return $val !== '' ? $val : null;
    }

    /**
     * Dublin Core from the first dmdSec that carries a DC or DCTERMS payload.
     * Handles both `dc:` and `dcterms:` element namespaces. Returns a map of
     * local element name => value; repeated elements are newline-joined.
     *
     * @return array<string,string>
     */
    private function extractDublinCore(DOMXPath $xp): array
    {
        $out = [];

        // Any element in the DC or DCTERMS namespace, anywhere in the doc
        // (typically under mets:dmdSec//mets:xmlData).
        $nodes = $xp->query(
            '//mets:dmdSec//*[namespace-uri()="' . self::NS_DC . '"'
            . ' or namespace-uri()="' . self::NS_DCTERMS . '"]'
        );

        if ($nodes === false || $nodes->length === 0) {
            // Fallback: some METS place DC outside dmdSec.
            $nodes = $xp->query(
                '//*[namespace-uri()="' . self::NS_DC . '"'
                . ' or namespace-uri()="' . self::NS_DCTERMS . '"]'
            );
        }

        if ($nodes === false) {
            return $out;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $key = $node->localName;
            $value = trim($node->textContent);
            if ($key === '' || $value === '') {
                continue;
            }
            $out[$key] = isset($out[$key]) ? $out[$key] . "\n" . $value : $value;
        }

        return $out;
    }

    /**
     * fileSec files whose fileGrp @USE is "access" (Archivematica DIP access
     * derivatives). Each carries its FLocat href + any inline fixity.
     *
     * @return array<int,array<string,mixed>>
     */
    private function extractAccessFiles(DOMXPath $xp): array
    {
        $out = [];

        $files = $xp->query(
            '//mets:fileSec/mets:fileGrp[translate(@USE,'
            . '"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="access"]/mets:file'
        );

        if ($files === false) {
            return $out;
        }

        foreach ($files as $file) {
            if (! $file instanceof DOMElement) {
                continue;
            }

            $href = null;
            $flocat = $xp->query('mets:FLocat', $file)->item(0);
            if ($flocat instanceof DOMElement) {
                $href = $flocat->getAttributeNS(self::NS_XLINK, 'href');
                if ($href === '') {
                    // Some writers drop the namespace prefix.
                    $href = $flocat->getAttribute('xlink:href') ?: $flocat->getAttribute('href');
                }
            }

            $out[] = [
                'file_id'       => $file->getAttribute('ID') ?: null,
                'use'           => 'access',
                'href'          => $href !== '' ? $href : null,
                'mimetype'      => $file->getAttribute('MIMETYPE') ?: null,
                'checksum'      => $file->getAttribute('CHECKSUM') ?: null,
                'checksum_type' => $file->getAttribute('CHECKSUMTYPE') ?: null,
                'size'          => $file->getAttribute('SIZE') !== ''
                    ? (int) $file->getAttribute('SIZE')
                    : null,
                'admid'         => $file->getAttribute('ADMID') ?: null,
            ];
        }

        return $out;
    }

    /**
     * PREMIS object entries (from amdSec/techMD). Works for PREMIS v2 and v3 by
     * querying with a namespace-agnostic local-name() predicate.
     *
     * @return array<int,array<string,mixed>>
     */
    private function extractPremisObjects(DOMXPath $xp): array
    {
        $out = [];

        // techMD blocks carry ADMID linkage back to the file.
        $techMds = $xp->query('//mets:amdSec/mets:techMD');
        if ($techMds === false || $techMds->length === 0) {
            // Fall back to bare premis:object nodes anywhere.
            $objects = $xp->query('//*[local-name()="object" and '
                . '(namespace-uri()="' . self::NS_PREMIS_V3 . '"'
                . ' or namespace-uri()="' . self::NS_PREMIS_V2 . '")]');
            if ($objects !== false) {
                foreach ($objects as $obj) {
                    $entry = $this->parsePremisObject($xp, $obj);
                    if ($entry !== null) {
                        $out[] = $entry;
                    }
                }
            }

            return $out;
        }

        foreach ($techMds as $techMd) {
            if (! $techMd instanceof DOMElement) {
                continue;
            }
            $admId = $techMd->getAttribute('ID') ?: null;
            $obj = $xp->query('.//*[local-name()="object"]', $techMd)->item(0);
            if (! $obj instanceof DOMElement) {
                continue;
            }
            $entry = $this->parsePremisObject($xp, $obj);
            if ($entry !== null) {
                $entry['admid'] = $admId;
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Pull the fields we care about out of one PREMIS <object>.
     *
     * @return array<string,mixed>|null
     */
    private function parsePremisObject(DOMXPath $xp, DOMElement $obj): ?array
    {
        $text = function (string $local, DOMElement $ctx) use ($xp): ?string {
            $n = $xp->query('.//*[local-name()="' . $local . '"]', $ctx)->item(0);
            $v = $n ? trim($n->textContent) : '';

            return $v !== '' ? $v : null;
        };

        $entry = [
            'object_identifier'        => $text('objectIdentifierValue', $obj),
            'original_name'            => $text('originalName', $obj),
            'message_digest'           => $text('messageDigest', $obj),
            'message_digest_algorithm' => $text('messageDigestAlgorithm', $obj),
            'size'                     => $text('size', $obj) !== null ? (int) $text('size', $obj) : null,
            'format_name'              => $text('formatName', $obj),
            'puid'                     => $text('formatRegistryKey', $obj),
            'admid'                    => null,
        ];

        // Discard an all-null object (e.g. a representation-level stub).
        $meaningful = array_filter(
            $entry,
            static fn ($v) => $v !== null,
        );

        return ! empty($meaningful) ? $entry : null;
    }
}
