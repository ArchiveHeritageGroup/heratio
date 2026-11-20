<?php

/**
 * RecordTextService - Heratio ahg-search
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1763: the text inside a record's files, for the search index's
 * `transcript` field - OCR text, reviewed transcripts, audio/video
 * transcriptions, caption tracks, image alt text and the PDF page index.
 *
 * A record whose files privacy screening still withholds (heratio#1574) gets
 * no text: a name inside an unscreened file must not become findable by search.
 * The text is indexed when the record is (re)indexed, so a record cleared by
 * screening, or newly OCR'd, is searchable from its next reindex.
 *
 * ponytail: a PDF's own embedded text layer is not read here (it would mean
 * running pdftotext on every file in a full reindex); PDFs are covered once
 * they are OCR'd or page-indexed. Upgrade path: a cached text sidecar per file.
 */
class RecordTextService
{
    /** Cap per record, so one huge transcript cannot bloat the index. */
    public const MAX_CHARS = 200000;

    /** @return array<int,string> record id => text (records with none are absent) */
    public static function forRecords(array $ioIds): array
    {
        $ioIds = array_values(array_unique(array_map('intval', $ioIds)));
        if ($ioIds === []) {
            return [];
        }
        $gate = class_exists(\AhgPrivacy\Services\FileScreeningGate::class) ? app(\AhgPrivacy\Services\FileScreeningGate::class) : null;
        $ioIds = array_values(array_filter($ioIds, fn ($id) => ! $gate || ! $gate->withholds($id)));
        if ($ioIds === []) {
            return [];
        }

        // Every file of these records: masters and their derivatives.
        $dos = DB::table('digital_object as m')->whereIn('m.object_id', $ioIds)->pluck('m.object_id', 'm.id')->all();
        if ($dos) {
            foreach (DB::table('digital_object')->whereIn('parent_id', array_keys($dos))->pluck('parent_id', 'id') as $d => $p) {
                $dos[$d] = $dos[$p];
            }
        }
        $doIds = array_keys($dos);
        $parts = [];
        $add = function (int $io, ?string $text) use (&$parts) {
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
            if ($t !== '') {
                $parts[$io][] = $t;
            }
        };
        $byDo = function (string $table, string $col, ?callable $clean = null) use ($doIds, $dos, $add) {
            if (! $doIds || ! Schema::hasTable($table)) {
                return;
            }
            foreach (DB::table($table)->whereIn('digital_object_id', $doIds)->get(['digital_object_id', $col]) as $r) {
                $add($dos[$r->digital_object_id], $clean ? $clean($r->{$col}) : $r->{$col});
            }
        };

        $byDo('iiif_ocr_text', 'full_text');
        $byDo('media_transcription', 'full_text');
        $byDo('ahg_media_transcription', 'text');
        $byDo('media_caption_track', 'vtt_content', fn ($v) => self::vttText((string) $v));
        $byDo('image_alt_text', 'alt_text');

        // Reviewed transcripts, kept as a property of the file.
        if ($doIds && Schema::hasTable('property')) {
            $rows = DB::table('property as p')->join('property_i18n as pi', 'pi.id', '=', 'p.id')
                ->whereIn('p.object_id', $doIds)->where('p.name', 'transcript')->get(['p.object_id', 'pi.value']);
            foreach ($rows as $r) {
                $add($dos[$r->object_id], $r->value);
            }
        }

        // The PDF page index: its section titles and summaries.
        if (Schema::hasTable('ahg_pageindex_tree')) {
            foreach (DB::table('ahg_pageindex_tree')->whereIn('object_id', $ioIds)->where('status', 'ready')->get(['object_id', 'tree_json']) as $r) {
                $add((int) $r->object_id, implode(' ', self::treeText(json_decode((string) $r->tree_json, true))));
            }
        }

        $out = [];
        foreach ($parts as $io => $texts) {
            $out[$io] = mb_substr(implode("\n", array_unique($texts)), 0, self::MAX_CHARS);
        }

        return $out;
    }

    /** The spoken text of a WebVTT track, without the header, cue numbers and timings. */
    public static function vttText(string $vtt): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $vtt) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'WEBVTT') || str_starts_with($line, 'NOTE') || ctype_digit($line) || str_contains($line, '-->')) {
                continue;
            }
            $lines[] = $line;
        }

        return implode(' ', $lines);
    }

    /** @return list<string> titles and summaries from a page-index tree */
    private static function treeText($node): array
    {
        if (! is_array($node)) {
            return [];
        }
        $out = [];
        foreach (['title', 'summary', 'text'] as $k) {
            if (isset($node[$k]) && is_string($node[$k])) {
                $out[] = $node[$k];
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                array_push($out, ...self::treeText($v));
            }
        }

        return $out;
    }
}
