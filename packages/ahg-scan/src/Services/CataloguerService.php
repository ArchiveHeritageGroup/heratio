<?php

/**
 * CataloguerService - Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\Log;

/**
 * heratio#1196 - AI Cataloguer. Composes the existing AI services (HTR -> NER ->
 * LLM) into a first-pass draft archival description from a single scan, for human
 * review. It reads from the locked ahg-ai-services package (HtrService / NerService
 * / LlmService) via the container but does not modify it. Nothing is saved here -
 * the draft is returned for an archivist to accept/edit (the create step is separate).
 */
class CataloguerService
{
    /**
     * Draft a record from one scan image. Returns:
     *   ['ok'=>bool, 'transcription'=>string, 'title'=>string, 'scope_and_content'=>string,
     *    'persons'=>[], 'organizations'=>[], 'places'=>[], 'dates'=>[]]
     */
    public function draftFromImage(string $filePath): array
    {
        $out = [
            'ok' => false, 'transcription' => '', 'title' => '', 'scope_and_content' => '',
            'persons' => [], 'organizations' => [], 'places' => [], 'dates' => [],
        ];
        if (! is_file($filePath)) {
            return $out;
        }

        // 1) HTR (handwriting/typed text) via the gateway.
        $text = '';
        try {
            $body = app(\AhgAiServices\Services\HtrService::class)->extract($filePath, 'auto', 'all');
            $text = $this->textFromHtr($body);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] cataloguer HTR failed: '.$e->getMessage());
        }
        $out['transcription'] = $text;
        if (trim($text) === '') {
            // No readable text - still return so the UI can say "no text found".
            return $out;
        }

        // 2) NER - people / orgs / places / dates from the transcription.
        try {
            $ner = app(\AhgAiServices\Services\NerService::class)->extract($text);
            $out['persons'] = $this->names($ner['persons'] ?? []);
            $out['organizations'] = $this->names($ner['organizations'] ?? []);
            $out['places'] = $this->names($ner['places'] ?? []);
            $out['dates'] = $this->names($ner['dates'] ?? []);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] cataloguer NER failed: '.$e->getMessage());
        }

        // 3) LLM - draft an archival title + scope-and-content note, grounded in the text.
        [$title, $scope] = $this->draftTitleAndScope($text);
        $out['title'] = $title;
        $out['scope_and_content'] = $scope;
        $out['ok'] = true;

        return $out;
    }

    /** Pull a single text blob from the HTR gateway response, whatever its shape. */
    private function textFromHtr($body): string
    {
        if (is_string($body)) {
            return trim($body);
        }
        if (! is_array($body)) {
            return '';
        }
        foreach (['text', 'transcription', 'full_text', 'content', 'plain_text'] as $k) {
            if (! empty($body[$k]) && is_string($body[$k])) {
                return trim($body[$k]);
            }
        }
        // page/line arrays -> join
        foreach (['pages', 'lines', 'results'] as $k) {
            if (! empty($body[$k]) && is_array($body[$k])) {
                $parts = [];
                foreach ($body[$k] as $row) {
                    if (is_string($row)) { $parts[] = $row; }
                    elseif (is_array($row)) {
                        foreach (['text', 'transcription', 'content'] as $rk) {
                            if (! empty($row[$rk]) && is_string($row[$rk])) { $parts[] = $row[$rk]; break; }
                        }
                    }
                }
                if ($parts) { return trim(implode("\n", $parts)); }
            }
        }

        return '';
    }

    /** Normalise NER entity lists (which may be strings or {text/name/value} maps) to plain strings. */
    private function names(array $entities): array
    {
        $out = [];
        foreach ($entities as $e) {
            if (is_string($e)) { $v = $e; }
            elseif (is_array($e)) { $v = $e['text'] ?? $e['name'] ?? $e['value'] ?? null; }
            else { $v = null; }
            $v = is_string($v) ? trim($v) : '';
            if ($v !== '' && ! in_array($v, $out, true)) { $out[] = $v; }
        }

        return array_slice($out, 0, 25);
    }

    /** One grounded LLM call -> [title, scope_and_content]. Refuses to invent facts. */
    private function draftTitleAndScope(string $text): array
    {
        $excerpt = mb_substr($text, 0, 6000);
        $prompt = "You are an experienced archivist writing an ISAD(G) catalogue entry from a transcribed document. "
            ."Using ONLY the transcription below, produce:\n"
            ."TITLE: a concise archival title (a short noun phrase, max ~12 words, no quotes)\n"
            ."SCOPE: a 2 to 4 sentence scope-and-content note summarising what the document is and contains\n"
            ."Do not invent dates, names, places or facts that are not in the transcription. "
            ."If the text is too fragmentary to tell, say so plainly in SCOPE. Output exactly those two lines, nothing else.\n\n"
            ."TRANSCRIPTION:\n".$excerpt;

        try {
            $resp = (string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 320, 'temperature' => 0.4]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] cataloguer LLM failed: '.$e->getMessage());

            return ['', ''];
        }

        $title = '';
        $scope = '';
        if (preg_match('/TITLE:\s*(.+)/i', $resp, $m)) { $title = trim($m[1]); }
        if (preg_match('/SCOPE:\s*(.+)/is', $resp, $m)) { $scope = trim($m[1]); }
        // Fallbacks if the model ignored the format.
        if ($title === '' && $scope === '' && trim($resp) !== '') { $scope = trim($resp); }
        $title = trim(preg_replace('/^["\']|["\']$/', '', $title));

        return [mb_substr($title, 0, 200), $scope];
    }
}
