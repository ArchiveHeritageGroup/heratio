<?php

/**
 * Marc21BinaryEncoder - Transcode a MARCXML record into the MARC21 binary
 * exchange format (ISO 2709, the historical .mrc file format).
 *
 * Layout per ISO 2709 + MARC21 spec:
 *
 *   LEADER (24 bytes)
 *   DIRECTORY (12 bytes per field + 1-byte field terminator)
 *   FIELDS body (each field ends with 0x1E)
 *   RECORD_TERMINATOR (0x1D)
 *
 * Leader positions:
 *   00-04  record length (5 digits, zero-padded)
 *   05     record status   ('n' = new)
 *   06     record type     ('a' = language material)
 *   07     biblio level    ('c' = collection)
 *   08     type of control (' ')
 *   09     char coding     ('a' = UCS / UTF-8)
 *   10-11  indicator + subfield code count    ("22")
 *   12-16  base address of data (5 digits, zero-padded)
 *   17     encoding level  ('u' = unknown)
 *   18     desc. cataloguing form  ('i' = ISBD)
 *   19     multipart level (' ')
 *   20-23  entry map       ("4500")
 *
 * Directory entry per field: 3-byte tag + 4-byte length + 5-byte offset.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgMetadataExport\Services\Exporters;

class Marc21BinaryEncoder
{
    private const FIELD_TERMINATOR = "\x1E";

    private const RECORD_TERMINATOR = "\x1D";

    private const SUBFIELD_DELIMITER = "\x1F";

    public function getFormat(): string
    {
        return 'marc';
    }

    /**
     * Parse a MARCXML record and return its ISO 2709 binary form.
     * Lengths + offsets are computed in BYTES (octets) per the spec,
     * which matters for multibyte UTF-8 content in record fields.
     */
    public function encodeFromMarcxml(string $marcxml): string
    {
        $dom = new \DOMDocument;
        // Tolerate the XML declaration the MarcxmlSerializer emits.
        $loaded = @$dom->loadXML($marcxml);
        if (! $loaded) {
            throw new \RuntimeException('Marc21BinaryEncoder: invalid MARCXML input');
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $directory = '';
        $body = '';
        $offset = 0;

        // controlfields first (tags 001-009), then datafields. Use document
        // order — the MarcxmlSerializer emits them in tag order already.
        $controlFields = $xpath->query('//marc:controlfield');
        if ($controlFields !== false) {
            foreach ($controlFields as $cf) {
                $tag = $cf->getAttribute('tag');
                $value = $cf->textContent;
                $field = $value.self::FIELD_TERMINATOR;
                $directory .= $this->directoryEntry($tag, strlen($field), $offset);
                $body .= $field;
                $offset += strlen($field);
            }
        }

        $dataFields = $xpath->query('//marc:datafield');
        if ($dataFields !== false) {
            foreach ($dataFields as $df) {
                $tag = $df->getAttribute('tag');
                $ind1 = $df->getAttribute('ind1');
                $ind2 = $df->getAttribute('ind2');
                // Indicators are always single chars; empty becomes space.
                $ind1 = $ind1 === '' ? ' ' : substr($ind1, 0, 1);
                $ind2 = $ind2 === '' ? ' ' : substr($ind2, 0, 1);
                $fieldBody = $ind1.$ind2;

                $subfields = $xpath->query('marc:subfield', $df);
                if ($subfields !== false) {
                    foreach ($subfields as $sf) {
                        $code = $sf->getAttribute('code') ?: 'a';
                        $val = $sf->textContent;
                        $fieldBody .= self::SUBFIELD_DELIMITER.$code.$val;
                    }
                }
                $field = $fieldBody.self::FIELD_TERMINATOR;
                $directory .= $this->directoryEntry($tag, strlen($field), $offset);
                $body .= $field;
                $offset += strlen($field);
            }
        }

        // Directory itself ends with a field terminator.
        $directory .= self::FIELD_TERMINATOR;

        $baseAddress = 24 + strlen($directory);
        $recordLength = $baseAddress + strlen($body) + 1; // +1 for record terminator

        $leader = $this->buildLeader($recordLength, $baseAddress);

        return $leader.$directory.$body.self::RECORD_TERMINATOR;
    }

    /**
     * Build a single 12-byte directory entry: 3-byte tag, 4-byte length,
     * 5-byte starting offset (all zero-padded ASCII digits).
     */
    private function directoryEntry(string $tag, int $length, int $offset): string
    {
        // MARC tags are 3 chars (digits or alpha — alphas are rare). Pad
        // or truncate defensively.
        $tag = str_pad(substr($tag, 0, 3), 3);

        return $tag
            .str_pad((string) $length, 4, '0', STR_PAD_LEFT)
            .str_pad((string) $offset, 5, '0', STR_PAD_LEFT);
    }

    private function buildLeader(int $recordLength, int $baseAddress): string
    {
        return str_pad((string) $recordLength, 5, '0', STR_PAD_LEFT)
             .'n'   // pos 05 record status
             .'a'   // pos 06 type of record (language material)
             .'c'   // pos 07 bibliographic level (collection)
             .' '   // pos 08 type of control
             .'a'   // pos 09 character coding (UTF-8/UCS)
             .'22'  // pos 10-11 indicator + subfield code counts
             .str_pad((string) $baseAddress, 5, '0', STR_PAD_LEFT)
             .'u'   // pos 17 encoding level (unknown)
             .'i'   // pos 18 descriptive cataloguing form (ISBD)
             .' '   // pos 19 multipart resource level
             .'4500'; // pos 20-23 entry map
    }
}
