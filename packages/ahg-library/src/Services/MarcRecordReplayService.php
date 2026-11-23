<?php

/**
 * MarcRecordReplayService - re-applies the current MARC field mapping to a
 * stored import record (#1476). Planning writes nothing. Applying writes only
 * the columns named by the caller, so a cataloguer's correction is never
 * overwritten by default.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;

class MarcRecordReplayService
{
    /** library_item columns the mapping owns => maximum length. */
    public const COLUMNS = [
        'subtitle' => 500, 'isbn' => 17, 'issn' => 9, 'pagination' => 100,
        'publisher' => 255, 'publication_place' => 255, 'publication_date' => 100,
        'call_number' => 100, 'classification_scheme' => 50, 'material_type' => 50,
        'marc_leader' => 24, 'marc_005' => 16, 'marc_008' => 40,
    ];

    public function __construct(
        private MarcRecordStore $store = new MarcRecordStore(),
        private Marc21DecoderService $decoder = new Marc21DecoderService(),
    ) {
    }

    /**
     * @return array{source: ?object, replayable: bool, fields: array<int, array{column: string, current: ?string, mapped: string, status: string}>}
     *         status: same | fill | conflict | unusable
     */
    public function plan(int $libraryItemId): array
    {
        $source = $this->store->replaySource($libraryItemId);
        $item = DB::table('library_item')->where('id', $libraryItemId)->first();
        if (! $source || ! $item) {
            return ['source' => $source, 'replayable' => false, 'fields' => []];
        }

        $parsed = $this->parse($source);
        // The mapping invents a default material type, so an empty parse must stop here.
        if ($parsed['control'] === [] && $parsed['data'] === []) {
            return ['source' => $source, 'replayable' => false, 'fields' => []];
        }

        $mapped = $this->decoder->mapToLibraryData($parsed);
        // No MARCXML form means MARC-8 or malformed bytes: only plain ASCII can be trusted.
        $undecoded = $source->marcxml === null;
        $fields = [];
        foreach (self::COLUMNS as $column => $max) {
            $value = $mapped[$column] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $value = (string) $value;
            $current = $item->{$column} ?? null;
            $current = $current === null ? null : (string) $current;

            $status = match (true) {
                $undecoded && preg_match('/[^\x20-\x7E]/', $value) === 1 => 'unusable',
                ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max => 'unusable',
                $current === $value => 'same',
                $current === null || trim($current) === '' => 'fill',
                default => 'conflict',
            };
            $fields[] = [
                'column' => $column,
                'current' => $current,
                'mapped' => $status !== 'unusable' || mb_check_encoding($value, 'UTF-8') && ! $undecoded ? $value : '',
                'status' => $status,
            ];
        }
        return ['source' => $source, 'replayable' => true, 'fields' => $fields];
    }

    /** @param string[] $columns @return int number of columns written */
    public function apply(int $libraryItemId, array $columns): int
    {
        $updates = [];
        $before = [];
        foreach ($this->plan($libraryItemId)['fields'] as $field) {
            if (in_array($field['column'], $columns, true) && in_array($field['status'], ['fill', 'conflict'], true)) {
                $updates[$field['column']] = $field['mapped'];
                $before[$field['column']] = $field['current'];
            }
        }
        if ($updates === []) {
            return 0;
        }

        $ioId = (int) DB::table('library_item')->where('id', $libraryItemId)->value('information_object_id');
        $this->store->ensureBaseline($libraryItemId);
        DB::transaction(function () use ($libraryItemId, $ioId, $updates) {
            DB::table('library_item')->where('id', $libraryItemId)->update($updates + ['updated_at' => now()]);
            DB::table('object')->where('id', $ioId)->update(['updated_at' => now()]);
        });
        \AhgCore\Support\AuditLog::captureEdit($ioId, 'library_item', $before, $updates);
        $this->store->snapshot($libraryItemId, 'replay');

        return count($updates);
    }

    /** Columns that are empty today and have a mapped value. */
    public function fillableColumns(int $libraryItemId): array
    {
        $fields = array_filter($this->plan($libraryItemId)['fields'], fn ($f) => $f['status'] === 'fill');
        return array_column($fields, 'column');
    }

    private function parse(object $source): array
    {
        // The binary import read these bytes with this decoder, so replay matches it exactly.
        if ($source->source_format === 'iso2709') {
            return $this->decoder->decode((string) $source->source_binary);
        }
        if ($source->marcxml === null) {
            return ['leader' => '', 'control' => [], 'data' => []];
        }
        $xml = (string) $source->marcxml;
        // Some clients send records without the MARC namespace.
        if (! str_contains($xml, 'loc.gov/MARC21/slim')) {
            $xml = (string) preg_replace('/<(collection|record)\b/', '<$1 xmlns="http://www.loc.gov/MARC21/slim"', $xml, 1);
        }
        return $this->restoreFixedFields((new MarcEditService())->parseMarcxml($xml), $xml);
    }

    /**
     * parseMarcxml trims every value, but blanks are significant in the leader
     * and 00X fields (a valid 008 can end in them). Text holding a line break
     * or tab is pretty-printing, so its trimmed form stands.
     */
    private function restoreFixedFields(array $parsed, string $xml): array
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return $parsed;
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $exact = fn (string $text) => trim($text) !== '' && strpbrk($text, "\r\n\t") === false;

        $leader = $xpath->query('//marc:leader')->item(0);
        if ($leader && $exact($leader->textContent)) {
            $parsed['leader'] = $leader->textContent;
        }
        foreach ($xpath->query('//marc:controlfield') as $node) {
            $tag = $node->getAttribute('tag');
            if ($tag !== '' && $exact($node->textContent)) {
                $parsed['control'][$tag] = $node->textContent;
            }
        }
        return $parsed;
    }
}
