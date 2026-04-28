<?php

/**
 * DiscoverySimulatedQueryService — generates a reproducible, ground-truthed
 * query corpus for ablation analysis without waiting on real users or LLM
 * paraphrase. Issue #11 (paper deliverable).
 *
 * Four deterministic generators, default totals matching the issue body:
 *   A. title-derived (n=30) — pick IOs, use title slice as query, ground truth = source IO
 *   B. subject-AP    (n=40) — top subject terms (taxonomy 35), ground truth = all IOs tagged with that term
 *   C. scope-NP      (n=20) — rule-based noun-phrase from scope_and_content; ground truth = source IO
 *   D. typo/abbrev   (n=10) — curated SA/ANC variant pairs; ground truth = canonical-form match set
 *
 * All four run without Ollama; C is the slot to swap when LLM paraphrase
 * comes online (becomes C.2 — out of scope until GPU returns).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class DiscoverySimulatedQueryService
{
    /** Per-generator default counts; total = 100, configurable via generate(). */
    private const DEFAULT_COUNTS = ['title' => 30, 'subject' => 40, 'scope_np' => 20, 'typo' => 10];

    /** Curated SA/ANC abbrev/typo pairs for generator D. Canonical → variants. */
    private const TYPO_PAIRS = [
        'African National Congress' => ['ANC', 'A.N.C.', 'Afrcian National Congress'],
        'South African Communist Party' => ['SACP', 'S.A.C.P.'],
        'Pan Africanist Congress' => ['PAC', 'Pan Africanist'],
        'Inkatha Freedom Party' => ['IFP', 'Inkatha'],
        'Soweto' => ['SOWETO', 'Sowetto'],
        'Mandela' => ['Mandella', 'Madiba', 'N. Mandela'],
        'apartheid' => ['Apartheid', 'aparthied'],
        'Umkhonto we Sizwe' => ['MK', 'Umkonto we Sizwe'],
        'Department of Education' => ['Dept of Education', 'Education Department'],
        'Soweto Uprising' => ['1976 Soweto', 'June 16'],
    ];

    /**
     * Build the full simulated corpus. Returns an ordered list of
     * record arrays, ready to write into ahg_discovery_simulated_run
     * or to convert into a qrels CSV.
     *
     * Each record: [
     *   'query_id' => 'q001',
     *   'query_text' => '...',
     *   'query_type' => 'title|subject|scope_np|typo',
     *   'expected_object_ids' => [int, ...],   // ground truth
     * ]
     *
     * @param array{title?:int,subject?:int,scope_np?:int,typo?:int} $counts
     * @param int $seed RNG seed for reproducibility
     */
    public function generate(array $counts = [], int $seed = 42): array
    {
        $counts = array_merge(self::DEFAULT_COUNTS, $counts);
        mt_srand($seed);

        $records = [];
        $i = 0;

        foreach ($this->titleQueries($counts['title']) as $r)    { $records[] = $this->withId(++$i, $r); }
        foreach ($this->subjectQueries($counts['subject']) as $r){ $records[] = $this->withId(++$i, $r); }
        foreach ($this->scopeNpQueries($counts['scope_np']) as $r){ $records[] = $this->withId(++$i, $r); }
        foreach ($this->typoQueries($counts['typo']) as $r)      { $records[] = $this->withId(++$i, $r); }

        return $records;
    }

    private function withId(int $i, array $r): array
    {
        return ['query_id' => sprintf('q%03d', $i)] + $r;
    }

    /**
     * A. Title-derived. Ground truth = the source IO id only (high precision,
     * known-item retrieval). Uses a 3-word slice of the title to avoid
     * trivially-matching the full title via exact phrase.
     */
    private function titleQueries(int $n): array
    {
        if ($n <= 0) return [];

        // Sample non-trivial titles — exclude the bulk-loaded BOX/FLR/ITEM
        // identifier-style titles which are essentially identifier strings.
        $rows = DB::connection('atom')->select(
            "SELECT i.id, i18n.title
             FROM information_object i
             JOIN information_object_i18n i18n ON i18n.id=i.id AND i18n.culture='en'
             WHERE i18n.title IS NOT NULL
               AND CHAR_LENGTH(i18n.title) BETWEEN 12 AND 80
               AND i18n.title NOT LIKE '%BOX%FLR%'
               AND i18n.title NOT LIKE '%FLR%ITEM%'
               AND i.parent_id > 1
             ORDER BY RAND(?)
             LIMIT ?",
            [42, $n * 3]   // pull 3× then filter to land at exactly n
        );

        $out = [];
        foreach ($rows as $r) {
            $words = preg_split('/\s+/', trim((string) $r->title)) ?: [];
            $words = array_values(array_filter($words, fn($w) => strlen($w) >= 3));
            if (count($words) < 2) continue;
            $slice = implode(' ', array_slice($words, 0, min(3, count($words))));
            $out[] = [
                'query_text' => $slice,
                'query_type' => 'title',
                'expected_object_ids' => [(int) $r->id],
            ];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    /**
     * B. Subject access points. Pick top-used subject terms; ground truth =
     * every IO tagged with that subject (recall-friendly target).
     */
    private function subjectQueries(int $n): array
    {
        if ($n <= 0) return [];

        $rows = DB::connection('atom')->select(
            "SELECT otr.term_id, ti.name, COUNT(*) AS uses
             FROM object_term_relation otr
             JOIN term t       ON t.id = otr.term_id AND t.taxonomy_id = 35
             JOIN term_i18n ti ON ti.id = t.id AND ti.culture = 'en'
             WHERE ti.name IS NOT NULL AND CHAR_LENGTH(ti.name) BETWEEN 3 AND 60
               AND ti.name NOT REGEXP '^[A-Z]{1,4}$'   -- skip acronyms (covered by D)
             GROUP BY otr.term_id, ti.name
             HAVING uses BETWEEN 5 AND 5000  -- skip 1-offs and dominant tags
             ORDER BY uses DESC
             LIMIT ?",
            [$n * 4]
        );

        // Sample without replacement from the candidates (deterministic via mt_srand seed).
        shuffle($rows);
        $out = [];
        foreach (array_slice($rows, 0, $n) as $r) {
            $tagged = DB::connection('atom')
                ->table('object_term_relation')
                ->where('term_id', (int) $r->term_id)
                ->limit(50)   // cap ground-truth set per query
                ->pluck('object_id')->map(fn($v) => (int) $v)->all();
            $out[] = [
                'query_text' => trim((string) $r->name),
                'query_type' => 'subject',
                'expected_object_ids' => $tagged,
            ];
        }
        return $out;
    }

    /**
     * C. Scope noun-phrase (rule-based). Pull IOs whose scope_and_content has
     * a 2–4 word capitalised noun phrase, use that as the query.
     * Ground truth = the source IO (paraphrase-style retrieval target).
     */
    private function scopeNpQueries(int $n): array
    {
        if ($n <= 0) return [];

        $rows = DB::connection('atom')->select(
            "SELECT i.id, i18n.scope_and_content AS scope
             FROM information_object i
             JOIN information_object_i18n i18n ON i18n.id=i.id AND i18n.culture='en'
             WHERE i18n.scope_and_content IS NOT NULL
               AND CHAR_LENGTH(i18n.scope_and_content) BETWEEN 50 AND 1500
               AND i.parent_id > 1
             ORDER BY RAND(?)
             LIMIT ?",
            [42, $n * 5]
        );

        $out = [];
        foreach ($rows as $r) {
            $np = $this->extractNounPhrase((string) $r->scope);
            if ($np === null) continue;
            $out[] = [
                'query_text' => $np,
                'query_type' => 'scope_np',
                'expected_object_ids' => [(int) $r->id],
            ];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    /**
     * Rule-based noun phrase extractor — finds 2–4 consecutive capitalised
     * words that aren't all-caps stop noise. Cheap stand-in for spaCy's NP
     * chunker; paper's "scope-NP" generator slot.
     */
    private function extractNounPhrase(string $text): ?string
    {
        $text = strip_tags($text);
        // 2–4 capitalised words in a row; first word ≥3 chars.
        if (! preg_match_all('/\b([A-Z][a-z]{2,}(?:\s+[A-Z][a-z]+){1,3})\b/u', $text, $matches)) {
            return null;
        }
        $candidates = array_filter($matches[0], function ($c) {
            // Skip rote month-day-year fragments and common header strings.
            if (preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)\b/i', $c)) {
                return false;
            }
            if (preg_match('/^(Box|Folder|File|Item|Page|Reel|Series)\b/i', $c)) {
                return false;
            }
            return true;
        });
        $candidates = array_values($candidates);
        if (empty($candidates)) return null;
        // Deterministic pick: shortest 2–3 word phrase (better signal-to-noise).
        usort($candidates, fn($a, $b) => str_word_count($a) <=> str_word_count($b));
        return $candidates[0];
    }

    /**
     * D. Typo / abbreviation queries. Static curated list — each row picks a
     * variant, ground truth = IOs whose title or scope mentions any
     * canonical or variant form (recall target across morphological drift).
     */
    private function typoQueries(int $n): array
    {
        if ($n <= 0) return [];

        $pairs = self::TYPO_PAIRS;
        $keys = array_keys($pairs);
        shuffle($keys);
        $keys = array_slice($keys, 0, min($n, count($keys)));

        $out = [];
        foreach ($keys as $canonical) {
            $variants = $pairs[$canonical];
            // Pick a non-canonical variant as the query text (the tricky case).
            $variant = $variants[array_rand($variants)];
            // Ground truth: IOs whose title OR scope mentions the canonical
            // form. Title alone misses entities that are only ever in scope
            // (e.g. "Soweto" is rarely a title in this corpus but is
            // pervasive in scope text). Capped to 50 to keep recall@100
            // meaningful and to bound the cost of the LIKE on the larger
            // scope_and_content column.
            $expected = DB::connection('atom')
                ->table('information_object_i18n')
                ->where('culture', 'en')
                ->where(function ($q) use ($canonical) {
                    $q->where('title', 'LIKE', '%' . $canonical . '%')
                      ->orWhere('scope_and_content', 'LIKE', '%' . $canonical . '%');
                })
                ->limit(50)
                ->pluck('id')->map(fn($v) => (int) $v)->all();
            $out[] = [
                'query_text' => $variant,
                'query_type' => 'typo',
                'expected_object_ids' => $expected,
            ];
        }
        return $out;
    }
}
