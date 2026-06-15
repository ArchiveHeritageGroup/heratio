<?php

/**
 * InputLanguageDetector - Heratio ahg-core
 *
 * heratio#1275 (follow-up to #1273/#1208/#1211): detect the language a chat / ask
 * message was TYPED in, so a reply can be localized to the input language rather
 * than only the UI locale. Lightweight by design: a local function-word / strong-
 * marker classifier over the official South African languages plus English. It runs
 * entirely in-process - NO network call, and (hard rule, feedback_no_qwen_for_af) NO
 * qwen / LLM "what language is this" prompt. The detected locale is only ever used as
 * the TARGET for the sanctioned MT route (AnswerLocalizer); generation stays English.
 *
 * Conservative on purpose: English is the safe default, so detection only returns a
 * non-English language when the evidence is clear (a strong marker, or several common
 * words, and a strict margin over English). On any ambiguity it returns null and the
 * caller keeps the UI-locale / English default. Closely-related languages share many
 * function words; shared greetings (e.g. "dumela", "sawubona") are treated as weak
 * (weight 1) so a lone ambiguous word never mis-routes - it falls back to the default.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

class InputLanguageDetector
{
    /** A strong (near-unique) marker is worth this many common-word hits. */
    private const STRONG_WEIGHT = 3;

    /** Minimum winning score before we trust a non-English detection. */
    private const MIN_SCORE = 3;

    /** The winner must beat English by at least this margin (English-default bias). */
    private const MIN_MARGIN = 1;

    /**
     * Languages whose output is reachable via the sanctioned MT route. A detection
     * outside this set returns null (the caller keeps the default); even if it did
     * slip through, AnswerLocalizer fails soft to English - never qwen.
     */
    private const MT_SUPPORTED = ['af', 'zu', 'xh', 'nso', 'st', 'tn', 'ts', 've', 'ss', 'nr'];

    /**
     * Strong, near-unique markers per language (greetings, thanks/please, distinctive
     * grammatical particles). One of these is enough to trip detection on a short opener.
     *
     * @var array<string,array<int,string>>
     */
    private const STRONG = [
        'af'  => ['nie', 'asseblief', 'hoekom', "'n", 'ek', 'jy', 'baie', 'dankie', 'goeiedag', 'wanneer'],
        'zu'  => ['ngiyabonga', 'ngicela', 'ukuthi', 'ngubani', 'yini', 'ngoba', 'kanjani'],
        'xh'  => ['enkosi', 'molo', 'ndicela', 'ndiyabulela', 'yintoni', 'kwaye', 'kufuneka', 'ndiyacela'],
        'nso' => ['bjang', 'gomme', 'kudu'],
        'st'  => ['joang', 'haholo', 'leboha', 'empa', 'kea'],
        'tn'  => ['jang', 'mme', 'gape', 'thata'],
        'ts'  => ['avuxeni', 'khensa', 'leswaku', 'njhani', 'swinene', 'inkomu'],
        've'  => ['ndaa', 'livhuwa', 'uri', 'vhukuma', 'ngafhi', 'aa'],
        'ss'  => ['kutsi', 'futsi', 'kodvwa', 'ngiyabonga', 'ngicela'],
        'nr'  => ['lotjhani', 'ngiyathokoza', 'khulumani', 'lokha'],
    ];

    /**
     * Common function words per language (weight 1). Several together corroborate a
     * detection; deliberately overlapping across sister languages (the strong markers
     * and the margin rule do the disambiguation).
     *
     * @var array<string,array<int,string>>
     */
    private const COMMON = [
        'af'  => ['die', 'en', 'is', 'wat', 'watter', 'met', 'van', 'vir', 'het', 'was', 'om', 'te', 'dit', 'aan', 'sal', 'kan', 'moet', 'hoe', 'waar', 'ons', 'hulle', 'julle', 'oor', 'hierdie', 'wie', 'maar', 'of'],
        'zu'  => ['nga', 'uku', 'futhi', 'kodwa', 'kakhulu', 'lapho', 'into', 'kanye', 'sawubona', 'wena', 'mina'],
        'xh'  => ['nga', 'uku', 'kodwa', 'kakhulu', 'apho', 'into', 'kanye', 'ndi', 'wena', 'mna', 'kuba'],
        'nso' => ['ke', 'go', 'le', 'ka', 'ya', 'gore', 'eng', 'mang', 'kae', 'thusa', 'se', 'dumela', 'leboga'],
        'st'  => ['ke', 'ho', 'le', 'ka', 'ya', 'hore', 'eng', 'mang', 'kae', 'thusa', 'hape', 'dumela'],
        'tn'  => ['ke', 'go', 'le', 'ka', 'ya', 'gore', 'eng', 'mang', 'kae', 'thusa', 'dumela', 'leboga'],
        'ts'  => ['hi', 'ku', 'na', 'ya', 'yini', 'mani', 'kwihi', 'pfuna', 'kambe', 'naswona'],
        've'  => ['ndi', 'na', 'ya', 'mini', 'hani', 'nnyi', 'thusa', 'fhedzi', 'vha'],
        'ss'  => ['nga', 'ku', 'kakhulu', 'wena', 'mine', 'sawubona', 'yini', 'kanjani'],
        'nr'  => ['nga', 'uku', 'kakhulu', 'wena', 'mina', 'into', 'kanye', 'lokho'],
        'en'  => ['the', 'and', 'is', 'are', 'was', 'what', 'where', 'when', 'how', 'why', 'who', 'with', 'for', 'from', 'this', 'that', 'can', 'you', 'please', 'tell', 'show', 'find', 'about', 'of', 'to', 'in', 'do', 'does'],
    ];

    /**
     * Detect the language of a typed message.
     *
     * @return string|null  an MT-supported, non-English locale code (e.g. 'af', 'zu')
     *                       when detection is confident; null otherwise (caller keeps
     *                       the UI-locale / English default).
     */
    public function detect(?string $text): ?string
    {
        $tokens = $this->tokenize((string) $text);
        if (count($tokens) === 0) {
            return null;
        }
        $set = array_flip($tokens);   // O(1) membership; dedupe so spam-repeats do not stack

        $scores = [];
        foreach (self::COMMON as $lang => $words) {
            $score = 0;
            foreach ($words as $w) {
                if (isset($set[$w])) {
                    $score++;
                }
            }
            $scores[$lang] = $score;
        }
        foreach (self::STRONG as $lang => $markers) {
            foreach ($markers as $w) {
                if (isset($set[$w])) {
                    $scores[$lang] = ($scores[$lang] ?? 0) + self::STRONG_WEIGHT;
                }
            }
        }

        $enScore = $scores['en'] ?? 0;

        // Best non-English candidate.
        $bestLang = null;
        $bestScore = 0;
        foreach ($scores as $lang => $score) {
            if ($lang === 'en') {
                continue;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLang = $lang;
            }
        }

        if ($bestLang === null
            || $bestScore < self::MIN_SCORE
            || ($bestScore - $enScore) < self::MIN_MARGIN
            || ! in_array($bestLang, self::MT_SUPPORTED, true)) {
            return null;   // ambiguous / English-leaning / unsupported -> default
        }

        return $bestLang;
    }

    /**
     * Lowercased word tokens. Keeps unicode letters and the apostrophe (so the
     * Afrikaans article "'n" survives); splits on everything else.
     *
     * @return array<int,string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }
        $parts = preg_split("/[^\\p{L}']+/u", $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== "'" && mb_strlen($t) >= 1));
    }
}
