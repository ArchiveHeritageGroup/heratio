<?php

/**
 * AskCollectionService - Heratio ahg-core
 *
 * heratio#1208 north-star ("a culture you can talk to - corpus-grounded history").
 * First public slice: the collection-wide, anonymous cousin of the room-scoped
 * exhibition docent. A member of the public asks a plain-language question and
 * gets an answer grounded in the institution's own corpus via the sanctioned KM
 * (knowledge-management RAG) HTTP query surface at km.theahg.co.za - the public,
 * cross-agent endpoint (POST /api/ask, bearer-token auth), NEVER a direct GPU node.
 *
 * The whole point of this surface is to be grounded and honest: it returns the
 * KM answer plus its cited sources, or a plain "I don't have enough in the
 * collection to answer that" when KM is unconfigured, empty, down, or slow. It
 * NEVER throws (a dead/slow KM must not break a public page) and it passes KM's
 * own honest gap message through as not-confidently-grounded rather than dressing
 * it up as a confident answer. Reuses the existing heratio.km.* config keys.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskCollectionService
{
    /** Cap the question we forward to KM (keeps the public surface cheap + bounded). */
    private const MAX_QUESTION_CHARS = 500;

    /** Trim each returned source title/snippet so the page list stays tidy. */
    private const MAX_SOURCE_TITLE = 240;

    /** Honest fallback shown whenever we cannot confidently ground an answer. */
    private const NOT_GROUNDED_MESSAGE =
        "I don't have enough in this collection to answer that. Try rephrasing, or browse the catalogue directly.";

    /**
     * Phrases KM emits when the indexed corpus does not cover the question. When
     * the answer leads with one of these we surface it as honest-but-ungrounded
     * (grounded=false) rather than presenting it as a confident catalogue answer.
     */
    private const GAP_MARKERS = [
        "i don't have enough",
        'i do not have enough',
        "don't have enough information",
        'do not have enough information',
        'not enough information',
        "i couldn't find",
        'i could not find',
        "couldn't find any",
        'no relevant information',
        "isn't covered",
        'is not covered',
        "don't have information",
        'do not have information',
        'cannot answer',
        "can't answer",
        'unable to answer',
        'no information about',
    ];

    /**
     * Ask the collection a plain-language question, grounded in the KM corpus.
     *
     * Always returns a well-formed array and never throws:
     *
     *   [
     *     'answer'   => ?string  // the grounded answer, the honest gap message, or null
     *     'sources'  => array    // [['title'=>string,'url'=>?string,'score'=>?float], ...]
     *     'grounded' => bool      // true only when KM returned a confident, non-gap answer
     *   ]
     *
     * @param  string  $question  the visitor's question
     */
    public function ask(string $question, ?string $locale = null): array
    {
        $result = $this->resolve($question);
        $dynamic = (bool) ($result['_dynamic'] ?? false);
        unset($result['_dynamic']);

        if (! empty($result['answer'])) {
            $targetLocale = trim((string) ($locale ?? app()->getLocale()));
            if ($dynamic) {
                // heratio#1208/#1211: a dynamic KM answer - translate via the sanctioned
                // MT route (AnswerLocalizer -> gateway /translate), fail-soft to English,
                // never an LLM. Sources/titles stay as-is (proper nouns / catalogue handles).
                $result['answer'] = app(\AhgCore\Services\AnswerLocalizer::class)
                    ->localize((string) $result['answer'], $targetLocale);
            } else {
                // Our own boilerplate ("we don't know") - use the curated lang-file string
                // for the target locale (a hand-curated message beats rough MT for fixed UI).
                $result['answer'] = __((string) $result['answer'], [], $targetLocale);
            }
        }

        return $result;
    }

    /** Resolve the grounded answer in English (single KM call); ask() localizes it. */
    private function resolve(string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            return $this->notGrounded();
        }

        $base = rtrim((string) config('heratio.km.base_url', ''), '/');
        $key = trim((string) config('heratio.km.web_api_key', ''));

        // KM not wired (no base URL or no web key) -> degrade honestly, never guess.
        if ($base === '' || $key === '') {
            return $this->notGrounded();
        }

        $timeout = max(1, (int) config('heratio.km.timeout_seconds', 6));

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->connectTimeout(min($timeout, 3))
                ->timeout($timeout)
                ->post($base.'/api/ask', [
                    'question' => mb_substr($question, 0, self::MAX_QUESTION_CHARS),
                    'stream' => false,
                ]);

            if (! $resp->successful()) {
                Log::debug('[ahg-core] ask-collection: KM /api/ask returned HTTP '.$resp->status());

                return $this->notGrounded();
            }

            $answer = trim((string) ($resp->json('answer') ?? ''));
            if ($answer === '') {
                return $this->notGrounded();
            }

            // heratio#1274: defence in depth - the public, anonymous surface must NEVER expose
            // internal/technical KB content (raw SQL, schema, code fences, operator docs) that can
            // leak into a RAG answer. If the answer looks technical, suppress it and degrade to the
            // curated "we don't know" rather than show it. (The primary fix is KM-side web-role
            // source filtering; this is the belt-and-braces in-repo guard.)
            if ($this->looksTechnical($answer)) {
                Log::info('[ahg-core] ask-collection: suppressed a technical/internal answer on the public surface (#1274)');

                return $this->notGrounded();
            }

            $sources = $this->normaliseSources($resp->json('sources'));

            // If KM honestly reports a corpus gap, pass it through as the answer but
            // mark it NOT grounded so the page frames it as "we don't know", not fact.
            if ($this->looksLikeGap($answer)) {
                // KM honestly reports a corpus gap. Surface our OWN curated "we don't know"
                // boilerplate (localized via the lang files in ask(), not rough MT of KM's
                // English), while keeping any sources KM did return. #1208/#1211 curation.
                return [
                    'answer' => self::NOT_GROUNDED_MESSAGE,
                    'sources' => $sources,
                    'grounded' => false,
                    '_dynamic' => false,
                ];
            }

            return [
                'answer' => $answer,
                'sources' => $sources,
                // Treat an answer with no cited sources as not-confidently-grounded:
                // grounded answers should be able to point at where they came from.
                'grounded' => $sources !== [],
                '_dynamic' => true,   // KM-sourced text -> MT-localize (stripped in ask())
            ];
        } catch (\Throwable $e) {
            // Timeout, DNS, TLS, connection refused, malformed JSON - all soft.
            Log::debug('[ahg-core] ask-collection: KM unavailable, degrading honestly: '.$e->getMessage());

            return $this->notGrounded();
        }
    }

    /** The honest "we don't know" payload (no fabricated answer, no sources). */
    private function notGrounded(): array
    {
        return [
            'answer' => self::NOT_GROUNDED_MESSAGE,
            'sources' => [],
            'grounded' => false,
            '_dynamic' => false,   // our own boilerplate -> curated __() string, not MT
        ];
    }

    /**
     * Normalise KM's `sources` array into a small, view-friendly shape. KM returns
     * objects like {title, url, score}; we keep only those keys, trim titles, drop
     * empties, and de-duplicate by url|title so the public list stays clean.
     *
     * @param  mixed  $raw
     * @return array<int,array{title:string,url:?string,score:?float}>
     */
    private function normaliseSources($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $s) {
            if (! is_array($s)) {
                continue;
            }
            $title = trim((string) ($s['title'] ?? $s['source'] ?? $s['url'] ?? ''));
            $url = trim((string) ($s['url'] ?? ''));
            if ($title === '' && $url === '') {
                continue;
            }
            if ($title === '') {
                $title = $url;
            }
            if (mb_strlen($title) > self::MAX_SOURCE_TITLE) {
                $title = mb_substr($title, 0, self::MAX_SOURCE_TITLE).'...';
            }

            $dedupe = ($url !== '' ? $url : mb_strtolower($title));
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $score = null;
            if (isset($s['score']) && is_numeric($s['score'])) {
                $score = round((float) $s['score'], 3);
            }

            $out[] = [
                'title' => $title,
                'url' => $url !== '' ? $url : null,
                'score' => $score,
            ];
        }

        return $out;
    }

    /** True when the answer leads with one of KM's honest corpus-gap phrases. */
    private function looksLikeGap(string $answer): bool
    {
        // Only inspect the opening of the answer - a gap disclaimer leads, it does
        // not hide three paragraphs in. Avoids false positives on a long answer
        // that merely mentions "no information about X" as one aside.
        $head = mb_strtolower(mb_substr($answer, 0, 200));
        foreach (self::GAP_MARKERS as $marker) {
            if (mb_strpos($head, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * heratio#1274: does this answer contain internal/technical content that must never reach
     * a public, anonymous visitor (raw SQL, schema/table names, code fences, operator markers)?
     * These patterns are unambiguous on a museum "ask the collection" surface, so a match means
     * the RAG answer leaked technical KB and should be suppressed (degrade to "we don't know").
     */
    private function looksTechnical(string $answer): bool
    {
        $patterns = [
            '/```/',                                                   // any code fence
            '/\bSELECT\b[\s\S]{0,200}\bFROM\b/i',                      // a SQL SELECT ... FROM
            '/\b(INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\S+\s+SET|DROP\s+TABLE|ALTER\s+TABLE|CREATE\s+TABLE|TRUNCATE\s+TABLE)\b/i',
            '/\bFROM\s+\S+\s+WHERE\b/i',                               // SQL from/where
            '/\bWHERE\s+[\w.`]+\s*=/i',                                // SQL where clause
            '/\b(information_object|term_i18n|digital_object|acl_user_group|qubit_\w+)\b/i',   // internal AtoM/Heratio table/column names
            '/(php artisan|->where\(|::class\b|composer require|\/usr\/share\/nginx|\/opt\/ahg)/i',   // code / operator markers
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $answer)) {
                return true;
            }
        }

        return false;
    }
}
