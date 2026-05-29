<?php

/**
 * MarcValidationService - structural + semantic validation of MARC21 records
 * supplied as MARCXML.
 *
 * Complements the XSD well-formedness check in
 * AhgMetadataExport\Services\Importers\MarcXmlImporter::validate() with
 * MARC21-specific rules the schema cannot express:
 *
 *   - Leader 24-byte length and per-position byte checks, including the
 *     encoding-level byte (leader/17) and the descriptive-cataloguing-form
 *     byte (leader/18).
 *   - Tag existence: every controlfield/datafield carries a 3-character tag
 *     and control fields (00X) must not carry subfields.
 *   - Subfield-code validity (single lowercase letter or digit).
 *   - Indicator values (single char: digit, lowercase letter, or blank).
 *   - 008 fixed-field length (must be 40 characters for full conformance;
 *     shorter fields are flagged as a warning, not an error, because legacy
 *     records frequently truncate it).
 *
 * The service returns a structured report rather than throwing, so the API
 * and the Blade report view can render every problem found in one pass.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class MarcValidationService
{
    public const NS_MARC = 'http://www.loc.gov/MARC21/slim';

    /**
     * Valid record-status codes for leader/05.
     * (a c d n p) - LoC MARC21 bibliographic leader.
     *
     * @var array<int, string>
     */
    private const LEADER_05_STATUS = ['a', 'c', 'd', 'n', 'p'];

    /**
     * Valid type-of-record codes for leader/06.
     *
     * @var array<int, string>
     */
    private const LEADER_06_TYPE = ['a', 'c', 'd', 'e', 'f', 'g', 'i', 'j', 'k', 'm', 'o', 'p', 'r', 't'];

    /**
     * Valid bibliographic-level codes for leader/07.
     *
     * @var array<int, string>
     */
    private const LEADER_07_LEVEL = ['a', 'b', 'c', 'd', 'i', 'm', 's'];

    /**
     * Valid encoding-level codes for leader/17.
     * Blank = full level; the rest are abbreviations / less-than-full.
     *
     * @var array<int, string>
     */
    private const LEADER_17_ENCODING = [' ', '1', '2', '3', '4', '5', '7', '8', 'u', 'z'];

    /**
     * Valid descriptive-cataloguing-form codes for leader/18.
     *
     * @var array<int, string>
     */
    private const LEADER_18_FORM = [' ', 'a', 'c', 'i', 'n', 'u'];

    /**
     * Validate a MARCXML payload that may contain one or more <record>
     * elements. Returns a report keyed:
     *
     *   [
     *     'valid'   => bool,           // false if any record has an error
     *     'records' => [
     *        [
     *          'index'    => int,       // 0-based record position
     *          'title'    => ?string,   // 245$a if present
     *          'control_number' => ?string,
     *          'errors'   => string[],
     *          'warnings' => string[],
     *        ], ...
     *     ],
     *     'error_count'   => int,
     *     'warning_count' => int,
     *     'parse_error'   => ?string,   // set when XML is not well-formed
     *   ]
     *
     * @return array<string, mixed>
     */
    public function validate(string $xml): array
    {
        $report = [
            'valid'         => true,
            'records'       => [],
            'error_count'   => 0,
            'warning_count' => 0,
            'parse_error'   => null,
        ];

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $loaded = @$dom->loadXML($xml);
        if (! $loaded) {
            $msgs = [];
            foreach (libxml_get_errors() as $err) {
                $msgs[] = sprintf('line %d: %s', $err->line, trim($err->message));
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $report['valid'] = false;
            $report['parse_error'] = $msgs ? implode('; ', $msgs) : 'Not well-formed XML';

            return $report;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('marc', self::NS_MARC);
        $nodes = $xpath->query('//marc:record');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//record');
        }

        if ($nodes === false || $nodes->length === 0) {
            $report['valid'] = false;
            $report['parse_error'] = 'No <record> element found.';

            return $report;
        }

        $index = 0;
        foreach ($nodes as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }
            $recReport = $this->validateRecord($node, $index);
            $report['records'][] = $recReport;
            $report['error_count'] += count($recReport['errors']);
            $report['warning_count'] += count($recReport['warnings']);
            if (! empty($recReport['errors'])) {
                $report['valid'] = false;
            }
            $index++;
        }

        return $report;
    }

    /**
     * Validate a single <record> DOM element.
     *
     * @return array<string, mixed>
     */
    public function validateRecord(DOMElement $record, int $index = 0): array
    {
        $errors = [];
        $warnings = [];
        $title = null;
        $controlNumber = null;

        $leaderEl = null;
        $controlfields = [];
        $datafields = [];

        foreach ($record->childNodes as $child) {
            if (! ($child instanceof DOMElement)) {
                continue;
            }
            switch ($child->localName) {
                case 'leader':
                    $leaderEl = $child;
                    break;
                case 'controlfield':
                    $controlfields[] = $child;
                    break;
                case 'datafield':
                    $datafields[] = $child;
                    break;
            }
        }

        // ── Leader ──────────────────────────────────────────────────────
        if ($leaderEl === null) {
            $errors[] = 'Leader: missing <leader> element (required).';
        } else {
            $leader = $leaderEl->textContent;
            $this->checkLeader($leader, $errors, $warnings);
        }

        // ── Control fields (00X) ─────────────────────────────────────────
        $has008 = false;
        foreach ($controlfields as $cf) {
            $tag = $cf->getAttribute('tag');
            if (! $this->isValidTag($tag)) {
                $errors[] = "Controlfield: invalid tag '{$tag}' (must be 3 characters).";
                continue;
            }
            if (substr($tag, 0, 2) !== '00') {
                $errors[] = "Controlfield: tag '{$tag}' is not a control field tag (must be 001-009).";
            }
            // Control fields must not carry subfields.
            foreach ($cf->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 'subfield') {
                    $errors[] = "Controlfield {$tag}: control fields must not contain subfields.";
                    break;
                }
            }
            if ($tag === '001') {
                $controlNumber = trim($cf->textContent);
            }
            if ($tag === '008') {
                $has008 = true;
                $this->check008($cf->textContent, $errors, $warnings);
            }
            if ($tag === '006') {
                $len = strlen($cf->textContent);
                if ($len !== 18) {
                    $warnings[] = "Controlfield 006: expected 18 characters, found {$len}.";
                }
            }
            if ($tag === '007' && strlen($cf->textContent) < 2) {
                $warnings[] = 'Controlfield 007: should be at least 2 characters.';
            }
        }
        if (! $has008) {
            $warnings[] = 'Controlfield 008: fixed-length data element field is absent (recommended).';
        }

        // ── Data fields (01X-8XX) ────────────────────────────────────────
        $has245 = false;
        foreach ($datafields as $df) {
            $tag = $df->getAttribute('tag');
            if (! $this->isValidTag($tag)) {
                $errors[] = "Datafield: invalid tag '{$tag}' (must be 3 characters).";
                continue;
            }
            if (substr($tag, 0, 2) === '00') {
                $errors[] = "Datafield: tag '{$tag}' is reserved for control fields (use <controlfield>).";
            }

            // Indicators.
            $ind1 = $df->getAttribute('ind1');
            $ind2 = $df->getAttribute('ind2');
            // DOM returns '' for an absent attribute; MARCXML requires a single
            // char, conventionally a blank. Treat '' as a blank for tolerance
            // but flag any non-conforming multi-char / illegal value.
            if ($ind1 !== '' && ! $this->isValidIndicator($ind1)) {
                $errors[] = "Datafield {$tag}: ind1 '{$ind1}' is not a valid indicator value.";
            }
            if ($ind2 !== '' && ! $this->isValidIndicator($ind2)) {
                $errors[] = "Datafield {$tag}: ind2 '{$ind2}' is not a valid indicator value.";
            }

            // Subfields.
            $subfieldCount = 0;
            foreach ($df->childNodes as $c) {
                if (! ($c instanceof DOMElement) || $c->localName !== 'subfield') {
                    continue;
                }
                $subfieldCount++;
                $code = $c->getAttribute('code');
                if (! $this->isValidSubfieldCode($code)) {
                    $errors[] = "Datafield {$tag}: invalid subfield code '{$code}' "
                        . '(must be a single lowercase letter or digit).';
                }
            }
            if ($subfieldCount === 0) {
                $errors[] = "Datafield {$tag}: must contain at least one subfield.";
            }

            if ($tag === '245') {
                $has245 = true;
                if ($title === null) {
                    foreach ($df->childNodes as $c) {
                        if ($c instanceof DOMElement && $c->localName === 'subfield'
                            && $c->getAttribute('code') === 'a') {
                            $title = trim($c->textContent);
                            break;
                        }
                    }
                }
            }
        }

        if (! $has245) {
            $errors[] = 'Datafield 245: title statement is required but absent.';
        }

        return [
            'index'          => $index,
            'title'          => $title,
            'control_number' => $controlNumber,
            'errors'         => $errors,
            'warnings'       => $warnings,
        ];
    }

    /**
     * Per-byte leader validation.
     *
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function checkLeader(string $leader, array &$errors, array &$warnings): void
    {
        $len = strlen($leader);
        if ($len !== 24) {
            $errors[] = "Leader: must be exactly 24 characters, found {$len}.";
            // Pad/truncate a working copy so position checks below still run
            // and surface as many problems as possible in one pass.
            $leader = str_pad(substr($leader, 0, 24), 24);
        }

        $pos05 = $leader[5] ?? ' ';
        if (! in_array($pos05, self::LEADER_05_STATUS, true)) {
            $errors[] = "Leader/05 (record status): '{$pos05}' is not a valid code (expected one of a c d n p).";
        }

        $pos06 = $leader[6] ?? ' ';
        if (! in_array($pos06, self::LEADER_06_TYPE, true)) {
            $errors[] = "Leader/06 (type of record): '{$pos06}' is not a valid code.";
        }

        $pos07 = $leader[7] ?? ' ';
        if (! in_array($pos07, self::LEADER_07_LEVEL, true)) {
            $errors[] = "Leader/07 (bibliographic level): '{$pos07}' is not a valid code.";
        }

        $pos09 = $leader[9] ?? ' ';
        if ($pos09 !== 'a' && $pos09 !== ' ') {
            $warnings[] = "Leader/09 (character coding scheme): '{$pos09}' is unusual ('a' = Unicode, ' ' = MARC-8).";
        }

        // Encoding level - byte 17.
        $pos17 = $leader[17] ?? ' ';
        if (! in_array($pos17, self::LEADER_17_ENCODING, true)) {
            $errors[] = "Leader/17 (encoding level): '{$pos17}' is not a valid code "
                . '(expected blank, 1-5, 7, 8, u, or z).';
        }

        // Descriptive cataloguing form - byte 18.
        $pos18 = $leader[18] ?? ' ';
        if (! in_array($pos18, self::LEADER_18_FORM, true)) {
            $errors[] = "Leader/18 (descriptive cataloguing form): '{$pos18}' is not a valid code "
                . '(expected blank, a, c, i, n, or u).';
        }

        // Bytes 20-23 are the entry map and are fixed at "4500".
        $tail = substr($leader, 20, 4);
        if ($tail !== '4500') {
            $warnings[] = "Leader/20-23 (entry map): expected '4500', found '{$tail}'.";
        }
    }

    /**
     * 008 fixed-field length check. A fully conformant 008 is 40 characters.
     *
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function check008(string $value, array &$errors, array &$warnings): void
    {
        $len = strlen($value);
        if ($len === 40) {
            return;
        }
        if ($len < 40) {
            // Legacy/abbreviated records are common; warn rather than fail so
            // an otherwise-usable record still imports.
            $warnings[] = "Controlfield 008: should be 40 characters, found {$len} (truncated fixed field).";
        } else {
            $errors[] = "Controlfield 008: must not exceed 40 characters, found {$len}.";
        }
    }

    private function isValidTag(?string $tag): bool
    {
        return $tag !== null && (bool) preg_match('/^[0-9A-Za-z]{3}$/', $tag);
    }

    private function isValidIndicator(string $ind): bool
    {
        // Single character: blank, digit, or lowercase letter.
        return (bool) preg_match('/^[ 0-9a-z]$/', $ind);
    }

    private function isValidSubfieldCode(string $code): bool
    {
        // Single lowercase letter or digit.
        return (bool) preg_match('/^[a-z0-9]$/', $code);
    }
}
