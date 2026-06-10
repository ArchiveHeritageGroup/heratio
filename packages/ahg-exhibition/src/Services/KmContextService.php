<?php

/**
 * KmContextService - heratio#1185 — optional KM (knowledge-management RAG) grounding
 * for the conversational room docent.
 *
 * Thin, fail-soft client for the sanctioned KM HTTP query surface at
 * km.theahg.co.za (`POST /api/ask`, bearer-token auth - the documented public
 * cross-agent endpoint, NOT a direct GPU node). It returns a short grounded
 * snippet to enrich the docent's answer beyond the placed-object catalogue, and
 * degrades to null silently on any failure or timeout so the docent never breaks
 * when KM is slow, down, or unconfigured.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KmContextService
{
    /** Trim the returned snippet to keep the docent prompt compact. */
    private const MAX_SNIPPET_CHARS = 700;

    /**
     * Ask the KM RAG service a question and return a short, source-grounded
     * snippet, or null on any failure / timeout / empty answer.
     *
     * Never throws: a slow or dead KM must not break the docent, so every error
     * path returns null and the caller proceeds catalogue-only.
     *
     * @param  string  $question        the visitor's question (resolved upstream)
     * @param  int     $timeoutSeconds  hard wall-clock budget for the whole call
     */
    public function ask(string $question, int $timeoutSeconds = 6): ?string
    {
        $question = trim($question);
        if ($question === '') {
            return null;
        }

        $base = rtrim((string) config('heratio.km.base_url', ''), '/');
        $key = trim((string) config('heratio.km.web_api_key', ''));
        if ($base === '' || $key === '') {
            // No KM endpoint / key wired - skip silently (catalogue-only docent).
            return null;
        }

        $timeout = max(1, (int) $timeoutSeconds);

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->connectTimeout(min($timeout, 3))
                ->timeout($timeout)
                ->post($base.'/api/ask', [
                    'question' => mb_substr($question, 0, 300),
                    'stream' => false,
                ]);

            if (! $resp->successful()) {
                return null;
            }

            $answer = trim((string) ($resp->json('answer') ?? ''));
            if ($answer === '') {
                return null;
            }

            // KM normalises whitespace itself, but keep the snippet tight so it
            // stays clearly secondary to the placed-object catalogue.
            $answer = preg_replace('/\s+/', ' ', $answer) ?? $answer;
            if (mb_strlen($answer) > self::MAX_SNIPPET_CHARS) {
                $answer = mb_substr($answer, 0, self::MAX_SNIPPET_CHARS).'...';
            }

            return $answer;
        } catch (\Throwable $e) {
            // Timeout, DNS, TLS, connection refused, malformed JSON - all soft.
            Log::debug('KmContextService: KM grounding unavailable, proceeding catalogue-only: '.$e->getMessage());

            return null;
        }
    }
}
