<?php

/**
 * AnswerLocalizer - Heratio ahg-core
 *
 * heratio#1208 / #1211 north-star: translate an English AI answer (KM / docent)
 * into the visitor's language so the public "ask the collection" surface and the
 * exhibition docent answer in-language, not English-only.
 *
 * COMPLIANCE (hard rule, feedback_no_qwen_for_af): SA-language output must NEVER
 * come from a qwen LLM "reply in X" prompt (Dutch-flavoured / hallucinated drift).
 * This localizer therefore calls ONLY the sanctioned MT route through the AHG
 * gateway (ai.theahg.co.za/ai/v1/translate - argos/opus-mt for af + the SA-language
 * models). It never touches an LLM. On ANY miss (unsupported language, MT down,
 * empty result) it returns the original English - English is always the safe
 * output, the qwen path is never reached. Afrikaans rides its own built MT path;
 * the other SA languages route together; both stay off the LLM.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnswerLocalizer
{
    /** Cache successful translations for a day (answers are largely stable). */
    private const CACHE_TTL = 86400;

    /** Hard ceiling on the MT call so a slow translate never hangs a public page. */
    private const MAX_TIMEOUT = 12;

    /**
     * Translate an English answer into $locale via the gateway MT route, falling
     * back to the original English on any failure. Never calls an LLM.
     *
     * @param  string  $englishText  the answer as generated in English
     * @param  string|null  $locale  target locale (e.g. af, zu, nso); defaults to the app locale
     */
    public function localize(string $englishText, ?string $locale = null): string
    {
        $englishText = trim($englishText);
        $locale = trim((string) ($locale ?? app()->getLocale()));

        if ($englishText === '' || $locale === '' || $this->isEnglish($locale)) {
            return $englishText;
        }

        [$endpoint, $key] = $this->mtConfig();
        if ($endpoint === '' || $key === '') {
            // MT not wired -> stay English rather than risk a non-compliant path.
            return $englishText;
        }

        $cacheKey = 'ahg_answer_loc:'.$locale.':'.md5($englishText);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->connectTimeout(3)
                ->timeout($this->timeout())
                ->post($endpoint, [
                    'text' => $englishText,
                    'target' => $locale,
                    'source' => 'en',
                ]);

            // Unsupported language (e.g. the MT route 500s) or any non-2xx ->
            // return English. The qwen LLM fallback is deliberately never reached.
            if (! $resp->successful()) {
                Log::debug('[ahg-core] AnswerLocalizer: MT HTTP '.$resp->status().' for locale '.$locale.' - keeping English');

                return $englishText;
            }

            $translated = trim((string) (
                $resp->json('translation')
                ?? $resp->json('translated')
                ?? $resp->json('translatedText')
                ?? ''
            ));

            if ($translated === '') {
                return $englishText;
            }

            Cache::put($cacheKey, $translated, self::CACHE_TTL);

            return $translated;
        } catch (\Throwable $e) {
            // Timeout, DNS, TLS, connection refused, malformed JSON - all soft.
            Log::debug('[ahg-core] AnswerLocalizer: MT unavailable for locale '.$locale.', keeping English: '.$e->getMessage());

            return $englishText;
        }
    }

    /** True when the locale is English (or the app fallback) - a pure pass-through, no MT hop. */
    private function isEnglish(string $locale): bool
    {
        $l = strtolower($locale);
        $fallback = strtolower((string) config('app.fallback_locale', 'en'));

        return $l === 'en' || str_starts_with($l, 'en_') || str_starts_with($l, 'en-') || $l === $fallback && $fallback === 'en';
    }

    /**
     * Resolve the gateway MT endpoint + bearer key the same way the rest of the AI
     * stack does (ahg_ner_settings first, then ahg_ai_settings general api_key).
     *
     * @return array{0:string,1:string} [endpoint, key]
     */
    private function mtConfig(): array
    {
        $endpoint = '';
        $key = '';
        try {
            $endpoint = (string) DB::table('ahg_ner_settings')->where('setting_key', 'mt_endpoint')->value('setting_value');

            $key = (string) DB::table('ahg_ner_settings')->where('setting_key', 'api_key')->value('setting_value');
            if ($key === '') {
                $key = (string) DB::table('ahg_ai_settings')
                    ->where('feature', 'general')->where('setting_key', 'api_key')
                    ->value('setting_value');
            }
        } catch (\Throwable $e) {
            return ['', ''];
        }

        return [rtrim($endpoint, '/'), trim($key)];
    }

    /** MT timeout from settings, clamped so a public page never hangs on it. */
    private function timeout(): int
    {
        $t = 0;
        try {
            $t = (int) DB::table('ahg_ner_settings')->where('setting_key', 'mt_timeout')->value('setting_value');
        } catch (\Throwable $e) {
            $t = 0;
        }

        return max(3, min(self::MAX_TIMEOUT, $t ?: 10));
    }
}
