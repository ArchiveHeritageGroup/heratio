<?php

/**
 * MarcRecordStore - keeps the source MARC record of a library item (#1476).
 *
 * An import row holds what arrived: the exact bytes for a single-record file
 * or a binary record. One record of a batch file is the record element
 * re-serialised as UTF-8, so it is not marked verbatim and the batch file's
 * original bytes are not kept.
 * Edit, baseline and replay rows are generated from the catalogue, so they
 * show what Heratio held at that moment and are never treated as a source.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarcRecordStore
{
    public const TABLE = 'library_marc_record';
    private const NS = 'http://www.loc.gov/MARC21/slim';

    /** Store an imported payload against the item created from it. */
    public function captureImport(int $informationObjectId, string $raw, bool $batchRecord = false): ?int
    {
        if (! Schema::hasTable(self::TABLE)) {
            Log::warning('[MarcRecordStore] table missing, source record not stored', ['io' => $informationObjectId]);
            return null;
        }

        $itemId = (int) DB::table('library_item')->where('information_object_id', $informationObjectId)->value('id');
        if (! $itemId) {
            return null;
        }

        // The decoder's own test, so a record it decoded as binary is stored as binary.
        if ((new Marc21DecoderService())->detectSyntax($raw) === 'marc21') {
            $iso = $this->firstIsoRecord($raw);
            return $this->insert($itemId, 'import', 'iso2709', true, $this->isoToMarcxml($iso), $iso);
        }

        $format = $batchRecord ? 'marcxml_record' : 'marcxml';
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $this->insert($itemId, 'import', $format, ! $batchRecord, $raw, null);
        }

        // A file in another encoding cannot sit in a utf8mb4 column: the original
        // bytes are kept as they arrived, with a UTF-8 copy for diff and replay.
        $dom = new DOMDocument();
        $utf8 = null;
        if (@$dom->loadXML($raw, LIBXML_NONET)) {
            $dom->encoding = 'UTF-8';
            $utf8 = (string) $dom->saveXML();
        }
        return $this->insert($itemId, 'import', $format, ! $batchRecord, $utf8, $raw);
    }

    /** Standalone XML for one record of a batch file, namespace included. */
    public function recordXml(DOMElement $record): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->appendChild($doc->importNode($record, true));
        return (string) $doc->saveXML($doc->documentElement);
    }

    /** Keep the pre-edit state of a record that has no history yet. */
    public function ensureBaseline(int $libraryItemId): void
    {
        try {
            if (Schema::hasTable(self::TABLE)
                && ! DB::table(self::TABLE)->where('library_item_id', $libraryItemId)->exists()) {
                $this->snapshot($libraryItemId, 'baseline');
            }
        } catch (Throwable $e) {
            Log::error('[MarcRecordStore] baseline failed: ' . $e->getMessage());
        }
    }

    /**
     * Store the record as the catalogue now holds it. Skipped when only the
     * 005 timestamp differs from the latest version. A failure is logged and
     * never fails the cataloguer's save.
     */
    public function snapshot(int $libraryItemId, string $event): ?int
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return null;
            }
            $xml = (new MarcEditService())->exportLibraryItem($libraryItemId, (string) app()->getLocale());
            if ($xml === '') {
                return null;
            }
            $latest = DB::table(self::TABLE)->where('library_item_id', $libraryItemId)
                ->orderByDesc('version')->first(['id', 'marcxml']);
            if ($latest && $latest->marcxml !== null
                && $this->without005($latest->marcxml) === $this->without005($xml)) {
                return (int) $latest->id;
            }
            return $this->insert($libraryItemId, $event, 'generated', false, $xml, null);
        } catch (Throwable $e) {
            Log::error('[MarcRecordStore] snapshot failed: ' . $e->getMessage());
            return null;
        }
    }

    public function versions(int $libraryItemId): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }
        return DB::table(self::TABLE)->where('library_item_id', $libraryItemId)
            ->orderByDesc('version')
            ->get(['id', 'version', 'event', 'source_format', 'is_verbatim', 'content_sha256', 'byte_length', 'created_by', 'created_at', DB::raw('source_binary IS NOT NULL AS has_binary'), DB::raw('marcxml IS NOT NULL AS has_marcxml')])
            ->all();
    }

    public function version(int $libraryItemId, int $version): ?object
    {
        return DB::table(self::TABLE)->where('library_item_id', $libraryItemId)->where('version', $version)->first();
    }

    /** The record replay reads: the most recent import, never an edit. */
    public function replaySource(int $libraryItemId): ?object
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }
        return DB::table(self::TABLE)->where('library_item_id', $libraryItemId)
            ->where('event', 'import')->orderByDesc('version')->first();
    }

    /** @return array<string,string> code => label */
    public function labels(string $taxonomy): array
    {
        return DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('is_active', 1)
            ->orderBy('sort_order')->pluck('label', 'code')->all();
    }

    /**
     * Field-level comparison. Repeated fields count separately and order is
     * ignored, so a moved field is not reported as a change.
     *
     * @return array{removed: string[], added: string[], unchanged: int}
     */
    public function diff(string $fromXml, string $toXml): array
    {
        $from = $this->fieldLines($fromXml);
        $to = $this->fieldLines($toXml);
        $remaining = array_count_values($to);
        $removed = [];
        $unchanged = 0;
        foreach ($from as $line) {
            if (($remaining[$line] ?? 0) > 0) {
                $remaining[$line]--;
                $unchanged++;
            } else {
                $removed[] = $line;
            }
        }
        $added = [];
        foreach ($to as $line) {
            if (($remaining[$line] ?? 0) > 0) {
                $remaining[$line]--;
                $added[] = $line;
            }
        }
        return ['removed' => $removed, 'added' => $added, 'unchanged' => $unchanged];
    }

    /** @return string[] one display line per leader, control and data field */
    public function fieldLines(string $xml): array
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $record = $xpath->query('//*[local-name()="record"]')->item(0);
        if (! $record) {
            return [];
        }
        $lines = [];
        foreach ($xpath->query('*', $record) as $node) {
            switch ($node->localName) {
                case 'leader':
                    $lines[] = 'LDR ' . $node->textContent;
                    break;
                case 'controlfield':
                    $lines[] = $node->getAttribute('tag') . ' ' . $node->textContent;
                    break;
                case 'datafield':
                    $line = $node->getAttribute('tag') . ' '
                        . str_pad($node->getAttribute('ind1'), 1) . str_pad($node->getAttribute('ind2'), 1);
                    foreach ($xpath->query('*[local-name()="subfield"]', $node) as $sub) {
                        $line .= ' $' . $sub->getAttribute('code') . ' ' . $sub->textContent;
                    }
                    $lines[] = $line;
                    break;
            }
        }
        return $lines;
    }

    /**
     * Lossless MARCXML from a UTF-8 ISO 2709 record. Returns null for MARC-8 or
     * malformed records: the binary is still kept, and no converted text is
     * stored that could misrepresent it.
     */
    public function isoToMarcxml(string $iso): ?string
    {
        if (strlen($iso) < 25 || $iso[9] !== 'a' || ! mb_check_encoding($iso, 'UTF-8')) {
            return null;
        }
        $base = (int) substr($iso, 12, 5);
        if ($base < 25 || $base > strlen($iso) || $iso[$base - 1] !== "\x1E") {
            return null;
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $record = $doc->createElementNS(self::NS, 'record');
        $doc->appendChild($record);
        $record->appendChild($doc->createElementNS(self::NS, 'leader'))
            ->appendChild($doc->createTextNode(substr($iso, 0, 24)));

        for ($pos = 24; $pos + 12 <= $base - 1; $pos += 12) {
            $tag = substr($iso, $pos, 3);
            $length = substr($iso, $pos + 3, 4);
            $start = substr($iso, $pos + 7, 5);
            if (! ctype_digit($length) || ! ctype_digit($start) || (int) $length < 1
                || $base + (int) $start + (int) $length > strlen($iso)) {
                return null;
            }
            $field = substr($iso, $base + (int) $start, (int) $length - 1);

            if (str_starts_with($tag, '00')) {
                if ($this->hasXmlIllegal($field)) {
                    return null;
                }
                $node = $doc->createElementNS(self::NS, 'controlfield');
                $node->setAttribute('tag', $tag);
                $node->appendChild($doc->createTextNode($field));
                $record->appendChild($node);
                continue;
            }

            $node = $doc->createElementNS(self::NS, 'datafield');
            $node->setAttribute('tag', $tag);
            $node->setAttribute('ind1', substr($field, 0, 1));
            $node->setAttribute('ind2', substr($field, 1, 1));
            foreach (array_slice(explode("\x1F", substr($field, 2)), 1) as $chunk) {
                if ($chunk === '') {
                    continue;
                }
                $value = substr($chunk, 1);
                if ($this->hasXmlIllegal($chunk)) {
                    return null;
                }
                $sub = $doc->createElementNS(self::NS, 'subfield');
                $sub->setAttribute('code', $chunk[0]);
                $sub->appendChild($doc->createTextNode($value));
                $node->appendChild($sub);
            }
            $record->appendChild($node);
        }

        return (string) $doc->saveXML($record);
    }

    private function hasXmlIllegal(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1;
    }

    /** Only the first record: the binary import creates one item per file. */
    private function firstIsoRecord(string $raw): string
    {
        $length = (int) substr($raw, 0, 5);
        if ($length >= 24 && $length <= strlen($raw) && $raw[$length - 1] === "\x1D") {
            return substr($raw, 0, $length);
        }
        $end = strpos($raw, "\x1D");
        return $end === false ? $raw : substr($raw, 0, $end + 1);
    }

    private function without005(string $xml): string
    {
        return (string) preg_replace('#<(?:\w+:)?controlfield tag="005">[^<]*</(?:\w+:)?controlfield>\s*#', '', $xml);
    }

    private function insert(int $itemId, string $event, string $format, bool $verbatim, ?string $xml, ?string $binary): int
    {
        return DB::transaction(function () use ($itemId, $event, $format, $verbatim, $xml, $binary) {
            // Serialises version numbering per item.
            DB::table('library_item')->where('id', $itemId)->lockForUpdate()->value('id');
            $version = (int) DB::table(self::TABLE)->where('library_item_id', $itemId)->max('version') + 1;
            $content = $binary ?? (string) $xml;

            return (int) DB::table(self::TABLE)->insertGetId([
                'library_item_id' => $itemId,
                'version' => $version,
                'event' => $event,
                'source_format' => $format,
                'is_verbatim' => $verbatim ? 1 : 0,
                'marcxml' => $xml,
                'source_binary' => $binary,
                'content_sha256' => hash('sha256', $content),
                'byte_length' => strlen($content),
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }
}
