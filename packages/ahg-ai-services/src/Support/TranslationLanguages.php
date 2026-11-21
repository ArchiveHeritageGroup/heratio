<?php

/**
 * TranslationLanguages - Heratio ahg-ai-services
 *
 * heratio#1419 / #1444: one place for the translation language codes Heratio
 * sends to the AHG AI gateway (/ai/v1/translate), and for asking the gateway
 * which targets it can really serve.
 *
 * - normalise() turns any form an operator or a record carries (zu_ZA, zu-ZA,
 *   zul, zul_Latn, nbl, Sepedi's nso) into the short code the gateway contract
 *   uses: en, af, zu, xh, nso, tn, st, ts, ss, ve, nr. The gateway maps these
 *   to its model codes; Heratio never sends model-specific codes.
 * - gatewayLanguages() reads GET {gateway}/translate/languages (cached). The
 *   family code `bnt` counts only for the languages it was measured to serve
 *   (BNT_USABLE): on 21 Nov 2026 the gateway answered st, tn and ss with the
 *   same wrong sentence from that shared model.
 * - isUnsupportedResponse() recognises the gateway's "no translator" answers
 *   so callers can degrade cleanly instead of storing an error or a bad guess.
 *
 * isiNdebele (nr) is deliberately never mapped to Northern Ndebele (nd/nde):
 * they are different languages.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgAiServices\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TranslationLanguages
{
    /** Default gateway translate route. */
    public const GATEWAY_TRANSLATE = 'https://ai.theahg.co.za/ai/v1/translate';

    /** How long the gateway language list is trusted before asking again. */
    private const CACHE_TTL = 600;

    /**
     * The 11 official South African languages: gateway code => FLORES-200 code.
     * null = no FLORES-200 / NLLB-200 code exists (checked against the NLLB-200
     * tokenizer on 21 Nov 2026: neither ven_Latn nor nbl_Latn is in it).
     */
    public const SA_OFFICIAL = [
        'en'  => 'eng_Latn',
        'af'  => 'afr_Latn',
        'zu'  => 'zul_Latn',
        'xh'  => 'xho_Latn',
        'nso' => 'nso_Latn',
        'tn'  => 'tsn_Latn',
        'st'  => 'sot_Latn',
        'ts'  => 'tso_Latn',
        'ss'  => 'ssw_Latn',
        've'  => null,
        'nr'  => null,
    ];

    /** ISO 639-2/3 and FLORES forms that mean the same language. */
    private const ALIASES = [
        'eng' => 'en', 'afr' => 'af', 'zul' => 'zu', 'xho' => 'xh',
        'sot' => 'st', 'tsn' => 'tn', 'tso' => 'ts', 'ssw' => 'ss',
        'ven' => 've', 'nbl' => 'nr',
    ];

    /** Families the gateway may list that are NOT a real per-language model. */
    private const FAMILY_CODES = ['bnt', 'mul', 'nic', 'alv'];

    /**
     * Languages the gateway's shared `bnt` model was measured to serve usably
     * (9 and 21 Nov 2026, native review still pending): zu, xh, ts, sn. The
     * same model gave st, tn and ss one identical wrong sentence, so they are
     * left out. ponytail: hand-kept list; it stops mattering once the gateway
     * lists per-language codes (docs/technical/sa-translation-gateway-spec.md).
     */
    private const BNT_USABLE = ['zu', 'xh', 'ts', 'sn'];

    /**
     * Normalise a culture / language code to the gateway contract code.
     * Unknown codes come back as their lower-cased base subtag (fr_CA -> fr).
     */
    public static function normalise(string $code): string
    {
        $c = strtolower(trim($code));
        if ($c === '') {
            return '';
        }
        // FLORES form: zul_Latn -> zul
        if (preg_match('/^([a-z]{2,3})_[a-z]{4}$/', $c, $m)) {
            $c = $m[1];
        }
        $base = preg_split('/[-_]/', $c)[0] ?? $c;

        return self::ALIASES[$base] ?? $base;
    }

    public static function isSaOfficial(string $code): bool
    {
        return array_key_exists(self::normalise($code), self::SA_OFFICIAL);
    }

    /** FLORES-200 code for a language, or null when none exists (ve, nr). */
    public static function floresCode(string $code): ?string
    {
        return self::SA_OFFICIAL[self::normalise($code)] ?? null;
    }

    /** The translate URL, ending in /translate exactly once. */
    public static function translateUrl(?string $endpoint): string
    {
        $e = rtrim(trim((string) $endpoint), '/');
        if ($e === '') {
            return self::GATEWAY_TRANSLATE;
        }

        return str_ends_with($e, '/translate') ? $e : $e . '/translate';
    }

    /** The language-list URL that goes with a translate endpoint. */
    public static function languagesUrl(?string $endpoint): string
    {
        return self::translateUrl($endpoint) . '/languages';
    }

    /**
     * Codes the gateway says it serves, normalised, family codes removed.
     * Returns null when the list cannot be read (gateway down, legacy adapter
     * without the route) - callers then try the call and handle its answer.
     *
     * @return list<string>|null
     */
    public static function gatewayLanguages(?string $endpoint, ?string $key): ?array
    {
        $url = self::languagesUrl($endpoint);
        $cacheKey = 'ahg:mt:languages:' . md5($url);

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable) {
            // cache unavailable - read live
        }

        try {
            $req = Http::connectTimeout(3)->timeout(8)->acceptJson();
            if (is_string($key) && $key !== '') {
                $req = $req->withToken($key);
            }
            $resp = $req->get($url);
            if (! $resp->ok() || ! is_array($resp->json('languages'))) {
                return null;
            }
            $codes = [];
            foreach ($resp->json('languages') as $row) {
                $code = is_array($row) ? (string) ($row['code'] ?? '') : (string) $row;
                $code = self::normalise($code);
                if ($code === 'bnt') {
                    array_push($codes, ...self::BNT_USABLE);
                } elseif ($code !== '' && ! in_array($code, self::FAMILY_CODES, true)) {
                    $codes[] = $code;
                }
            }
            $codes = array_values(array_unique($codes));
            try {
                Cache::put($cacheKey, $codes, self::CACHE_TTL);
            } catch (\Throwable) {
                // ignore
            }

            return $codes;
        } catch (\Throwable $e) {
            Log::debug('[ahg-ai] MT language list unavailable: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * True unless the gateway's own language list leaves the target out.
     * English and an unreadable list both count as "try it".
     */
    public static function gatewaySupports(string $target, ?string $endpoint, ?string $key): bool
    {
        $t = self::normalise($target);
        if ($t === '' || $t === 'en') {
            return true;
        }
        $codes = self::gatewayLanguages($endpoint, $key);

        return $codes === null || in_array($t, $codes, true);
    }

    /**
     * The gateway's "cannot translate this pair" answers: 400/404/422/500 with
     * TRANSLATION_FAILED or an unsupported / no-translator message.
     *
     * @param  mixed  $body  decoded JSON body
     */
    public static function isUnsupportedResponse(int $status, mixed $body): bool
    {
        if ($status < 400 || ! is_array($body)) {
            return false;
        }
        $code = strtoupper((string) ($body['code'] ?? ''));
        $msg = strtolower((string) ($body['error'] ?? (is_string($body['detail'] ?? null) ? $body['detail'] : '')));

        return $code === 'UNSUPPORTED_LANGUAGE'
            || str_contains($msg, 'no translator available')
            || str_contains($msg, 'unsupported target language')
            || str_contains($msg, 'unsupported source language')
            || str_contains($msg, 'not supported');
    }
}
