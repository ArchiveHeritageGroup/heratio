<?php

/**
 * Heratio - did-you-mean for the database search path (heratio#1583).
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * MySQL has no trigram operator, so the correction works from a small
 * vocabulary: the distinct words of the titles and access-point terms
 * (subjects, places, genres) of published, disclosable records. Drafts,
 * embargoed, classified, ICIP/ODRL-restricted and protocol-restricted records
 * contribute nothing, so a suggestion never reveals a word the visitor could
 * not find for themselves.
 *
 * The vocabulary is cached per culture (ahg_display_vocab_cache_ttl, default
 * one hour) and capped at MAX_WORDS of the most frequent words. It is grouped
 * by first letter and length, so a lookup only measures edit distance against
 * words that start with the same letter and are within two letters in length.
 */

namespace AhgDisplay\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DisclosureGate;
use AhgCore\Services\TermProtocolGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchVocabularyService
{
    /** Most frequent words kept. */
    public const MAX_WORDS = 50000;

    /**
     * ponytail: titles read per build. A catalogue larger than this builds
     * from its newest records only; a sidecar word table maintained on save
     * is the upgrade path if that ever matters.
     */
    public const MAX_TITLE_ROWS = 300000;

    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 30;

    /** Access-point taxonomies: subject, place, genre. */
    private const ACCESS_POINT_TAXONOMIES = [35, 42, 78];

    /**
     * The query with each misspelt word replaced by its closest vocabulary
     * word, or null when no word changes.
     */
    public function correct(string $query, string $culture): ?string
    {
        $vocab = null;
        $changed = false;
        $out = preg_replace_callback('/[\p{L}\p{M}]+/u', function ($m) use (&$vocab, &$changed, $culture) {
            $word = $m[0];
            $lower = mb_strtolower($word);
            $len = mb_strlen($lower);
            if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
                return $word;
            }
            $vocab ??= $this->vocabulary($culture);
            $best = $this->closest($lower, $vocab);
            if ($best === null) {
                return $word;
            }
            $changed = true;

            return preg_match('/^\p{Lu}/u', $word) ? mb_strtoupper(mb_substr($best, 0, 1)).mb_substr($best, 1) : $best;
        }, $query);

        return $changed ? $out : null;
    }

    /**
     * The closest word: same first letter, length within the edit budget,
     * fewest edits (one for words up to seven letters, else two), then the most
     * frequent. Null when the word is itself in the vocabulary or nothing is
     * close enough.
     *
     * @param  array<string,array<int,array<string,int>>>  $vocab
     */
    public function closest(string $lower, array $vocab): ?string
    {
        $first = mb_substr($lower, 0, 1);
        $len = mb_strlen($lower);
        $byLen = $vocab[$first] ?? [];
        if (isset($byLen[$len][$lower])) {
            return null;
        }
        // Two edits on a six-letter word is a different word too often
        // (mining -> morning), so short and medium words get one.
        $max = $len <= 7 ? 1 : 2;
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestFreq = -1;
        for ($l = $len - $max; $l <= $len + $max; $l++) {
            foreach ($byLen[$l] ?? [] as $cand => $freq) {
                $cand = (string) $cand;
                $d = levenshtein($lower, $cand);
                if ($d <= $max && ($d < $bestDistance || ($d === $bestDistance && $freq > $bestFreq))) {
                    [$best, $bestDistance, $bestFreq] = [$cand, $d, $freq];
                }
            }
        }

        return $best;
    }

    /**
     * The cached vocabulary: [first letter => [length => [word => frequency]]].
     * The key carries the restricted set, so an embargo starting or ending,
     * or a tenant boundary, gives a different vocabulary.
     *
     * @return array<string,array<int,array<string,int>>>
     */
    public function vocabulary(string $culture): array
    {
        $restricted = app(DisclosureGate::class)->restrictedIds();
        sort($restricted);
        $user = AclService::getUser();
        $protocolBypass = $user && (AclService::isAdministrator($user) || AclService::isEditor($user));
        $key = 'ahg_display:vocab:v1:'.$culture.':'.md5(implode(',', $restricted)).':p'.(int) $protocolBypass;

        $ttl = AhgSettingsService::getInt('ahg_display_vocab_cache_ttl', 3600);
        if ($ttl <= 0) {
            return $this->build($culture);
        }
        try {
            return Cache::remember($key, $ttl, fn () => $this->build($culture));
        } catch (\Throwable $e) {
            return $this->build($culture); // cache store unavailable
        }
    }

    /** @return array<string,array<int,array<string,int>>> */
    public function build(string $culture): array
    {
        $cultures = array_values(array_unique([$culture, (string) config('app.fallback_locale', 'en')]));
        $counts = [];

        $rows = 0;
        $titles = $this->disclosable()
            ->join('information_object_i18n as vi', 'vi.id', '=', 'io.id')
            ->whereIn('vi.culture', $cultures)
            ->whereNotNull('vi.title')
            ->select('io.id', 'vi.title');
        foreach ($titles->lazyByIdDesc(5000, 'io.id', 'id') as $r) {
            $this->addWords($counts, (string) $r->title, 1);
            if (++$rows >= self::MAX_TITLE_ROWS) {
                break;
            }
        }

        $terms = DB::table('object_term_relation as votr')
            ->join('term as vt', 'vt.id', '=', 'votr.term_id')
            ->join('term_i18n as vti', 'vti.id', '=', 'vt.id')
            ->whereIn('vt.taxonomy_id', self::ACCESS_POINT_TAXONOMIES)
            ->whereIn('vti.culture', $cultures)
            ->whereIn('votr.object_id', $this->disclosable()->select('io.id'))
            ->groupBy('vti.name')
            ->select('vti.name', DB::raw('COUNT(*) AS n'));
        TermProtocolGate::addTermVisibilityCriteria($terms, 'vt.id');
        foreach ($terms->get() as $r) {
            $this->addWords($counts, (string) $r->name, (int) $r->n);
        }

        arsort($counts);
        $vocab = [];
        foreach (array_slice($counts, 0, self::MAX_WORDS, true) as $word => $n) {
            $word = (string) $word;
            $vocab[mb_substr($word, 0, 1)][mb_strlen($word)][$word] = $n;
        }

        return $vocab;
    }

    /** Published records outside the disclosure and protocol restrictions. */
    private function disclosable()
    {
        $q = DB::table('information_object as io')->where('io.id', '>', 1);
        $gate = app(DisclosureGate::class);
        $gate->wherePublished($q, 'io');
        $gate->excludeRestricted($q, 'io.id');
        TermProtocolGate::excludeRestrictedRecords($q, 'io.id');

        return $q;
    }

    /** @param array<string,int> $counts */
    private function addWords(array &$counts, string $text, int $weight): void
    {
        if (! preg_match_all('/[\p{L}\p{M}]+/u', $text, $m)) {
            return;
        }
        foreach ($m[0] as $w) {
            $len = mb_strlen($w);
            if ($len >= self::MIN_LENGTH && $len <= self::MAX_LENGTH) {
                $w = mb_strtolower($w);
                $counts[$w] = ($counts[$w] ?? 0) + $weight;
            }
        }
    }
}
