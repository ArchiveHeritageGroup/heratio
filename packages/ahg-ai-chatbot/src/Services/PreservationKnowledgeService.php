<?php

/**
 * PreservationKnowledgeService
 *
 * Deterministic retrieval over Heratio's curated digital-preservation
 * knowledge corpus (the in-repo `docs/reference/dp-*.md` and the preservation
 * `docs/help/*.md` articles). Given a natural-language question it returns the
 * most relevant curated passages, each carrying a verbatim excerpt and a source
 * citation, so the AI assistant can ground preservation answers in authoritative
 * guidance instead of improvising.
 *
 * Design notes:
 *   - NO AI / LLM / embedding call is made here. Retrieval is a pure,
 *     deterministic keyword-and-section index over the curated markdown corpus.
 *     This keeps the layer verifiable and testable, and - by construction -
 *     never touches a GPU node port or the AHG gateway. The assistant's own
 *     generative turn continues to route through the existing LlmService /
 *     gateway abstraction unchanged; this service only supplies extra grounding
 *     context.
 *   - Passages are returned verbatim from the curated docs (no paraphrase, no
 *     fabricated guidance). Each passage cites its source file + heading.
 *   - Read-only over the corpus. No database access, no writes.
 *
 * International / jurisdiction-neutral: the corpus is standards-first (OAIS,
 * PREMIS, METS, BagIt, PRONOM, NDSA Levels, DPC RAM, OCFL, WARC) and country
 * compliance regimes sit alongside as pluggable modules.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Services;

class PreservationKnowledgeService
{
    /**
     * Glob patterns (relative to the repo base path) that define the curated
     * preservation corpus. Only these files are indexed - the retrieval layer
     * is deliberately scoped to the vetted digital-preservation knowledge set.
     *
     * @var string[]
     */
    private const CORPUS_GLOBS = [
        'docs/reference/dp-*.md',
        'docs/reference/*preservation*.md',
        'docs/reference/fixity-*.md',
        'docs/reference/premis-*.md',
        'docs/reference/web-archive-*.md',
        'docs/help/dp-*.md',
        'docs/help/preservation-*.md',
        'docs/help/fixity-*.md',
        'docs/help/premis-*.md',
        'docs/help/mets-*.md',
        'docs/help/ocfl-*.md',
        'docs/help/3d-preservation.md',
        'docs/help/preservation-user-guide.md',
    ];

    /**
     * Concept synonym / expansion map. Keys are canonical preservation terms;
     * values are alternate phrasings a user might type. When any token in a
     * group appears in the query, the whole group is treated as query terms so
     * "how often should I run integrity checks" still matches a "fixity" doc.
     * Plain, lower-case, ASCII; jurisdiction-neutral.
     *
     * @var array<int, string[]>
     */
    private const SYNONYMS = [
        ['fixity', 'checksum', 'checksums', 'hash', 'sha256', 'sha-256', 'sha512', 'md5', 'integrity', 'bit', 'rot', 'corruption', 'tamper'],
        ['premis', 'preservation', 'metadata', 'provenance', 'event', 'agent'],
        ['mets', 'packaging', 'structural', 'manifest'],
        ['bagit', 'bag', 'rfc8493', 'transfer'],
        ['pronom', 'puid', 'format', 'formats', 'identification', 'identify', 'fido', 'siegfried', 'droid'],
        ['oais', 'reference', 'model', 'iso14721', 'ingest', 'aip', 'sip', 'dip'],
        ['ndsa', 'levels', 'maturity', 'self-assessment'],
        ['dpc', 'ram', 'rapid', 'assessment', 'organisational', 'organizational'],
        ['significant', 'properties', 'normalisation', 'normalization', 'migration', 'render'],
        ['ocfl', 'storage', 'replication', 'replica', 'replicate', 'geographic', 'copies'],
        ['warc', 'web', 'archiving', 'crawl', 'heritrix', 'browsertrix', 'wayback', 'website'],
        ['schedule', 'scheduled', 'often', 'frequency', 'periodic', 'regular', 'recurring'],
    ];

    private string $basePath;

    /** @var array<int, array{source:string,heading:string,anchor:string,text:string,terms:array<string,int>}>|null */
    private ?array $index = null;

    public function __construct(?string $basePath = null)
    {
        // base_path() is available in a booted Laravel app; the constructor
        // argument lets tests point at a fixture corpus.
        $this->basePath = rtrim($basePath ?? $this->resolveBasePath(), '/');
    }

    /**
     * Retrieve the most relevant curated preservation passages for a query.
     *
     * @param  string $query  Natural-language question.
     * @param  int    $limit  Maximum passages to return.
     * @return array<int, array{
     *   title:string,
     *   source:string,
     *   heading:string,
     *   excerpt:string,
     *   score:float
     * }>
     */
    public function retrieve(string $query, int $limit = 3): array
    {
        $queryTerms = $this->expandTerms($this->tokenize($query));
        if (empty($queryTerms)) {
            return [];
        }

        $scored = [];
        foreach ($this->getIndex() as $section) {
            $score = $this->scoreSection($queryTerms, $section);
            if ($score > 0.0) {
                $scored[] = [
                    'title'   => $this->prettyTitle($section['source']),
                    'source'  => $section['source'] . '#' . $section['anchor'],
                    'heading' => $section['heading'],
                    'excerpt' => $this->trimExcerpt($section['text']),
                    'score'   => round($score, 4),
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * Decide whether a query looks like a digital-preservation question. Used
     * by the assistant to gate the supplementary grounding block. Conservative:
     * a single strong domain term is enough, because the block is supplementary
     * and the assistant still answers from its own sources too.
     */
    public function looksLikePreservationQuestion(string $query): bool
    {
        $terms = $this->tokenize($query);
        $strong = [
            'fixity', 'checksum', 'checksums', 'premis', 'mets', 'bagit', 'pronom',
            'puid', 'oais', 'ndsa', 'dpc', 'ocfl', 'warc', 'preservation', 'preserve',
            'preserving', 'integrity', 'normalisation', 'normalization', 'migration',
            'replication', 'replica', 'sip', 'aip', 'dip', 'significant',
            'format', 'formats', 'identification', 'identify', 'archiving', 'pronom',
        ];
        foreach ($terms as $t) {
            if (in_array($t, $strong, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a ready-to-inject grounding context block from retrieved passages,
     * or an empty string when nothing relevant is found. The caller appends this
     * to the system prompt as supplementary context (additive; it never replaces
     * the catalogue sources).
     */
    public function buildContextBlock(string $query, int $limit = 3): string
    {
        $passages = $this->retrieve($query, $limit);
        if (empty($passages)) {
            return '';
        }

        $lines = [
            "PRESERVATION KNOWLEDGE - curated digital-preservation guidance from "
            . "the Heratio knowledge base. Use these passages to answer "
            . "digital-preservation questions accurately, and cite them by their "
            . "(source: ...) tag. Do not invent preservation guidance beyond what "
            . "these passages state.\n",
        ];
        foreach ($passages as $i => $p) {
            $num = $i + 1;
            $lines[] = "[K{$num}] {$p['title']} - {$p['heading']}\n"
                . "   (source: {$p['source']})\n"
                . "   {$p['excerpt']}";
        }

        return "\n\n" . implode("\n", $lines);
    }

    // ─── Indexing ──────────────────────────────────────────────────

    /**
     * Lazily build (and cache) the section index over the curated corpus.
     *
     * @return array<int, array{source:string,heading:string,anchor:string,text:string,terms:array<string,int>}>
     */
    public function getIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];
        foreach ($this->corpusFiles() as $absPath) {
            $rel = $this->relativePath($absPath);
            $raw = @file_get_contents($absPath);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            foreach ($this->splitSections($raw) as $section) {
                $text = trim($section['text']);
                if ($text === '') {
                    continue;
                }
                $index[] = [
                    'source'  => $rel,
                    'heading' => $section['heading'],
                    'anchor'  => $this->slugifyAnchor($section['heading']),
                    'text'    => $text,
                    'terms'   => $this->termFrequencies($section['heading'] . ' ' . $text),
                ];
            }
        }

        return $this->index = $index;
    }

    /**
     * Resolve the curated corpus files from the glob allowlist, de-duplicated.
     *
     * @return string[]
     */
    public function corpusFiles(): array
    {
        $files = [];
        foreach (self::CORPUS_GLOBS as $glob) {
            $matches = glob($this->basePath . '/' . $glob) ?: [];
            foreach ($matches as $m) {
                if (is_file($m)) {
                    $files[$m] = true;
                }
            }
        }

        return array_keys($files);
    }

    /**
     * Split a markdown document into sections keyed by their ATX heading.
     * Content before the first heading is attached to the document title.
     *
     * @return array<int, array{heading:string, text:string}>
     */
    private function splitSections(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $sections = [];
        $currentHeading = 'Overview';
        $buffer = [];
        $docTitle = null;

        $flush = function () use (&$sections, &$currentHeading, &$buffer) {
            if (!empty($buffer)) {
                $sections[] = [
                    'heading' => $currentHeading,
                    'text'    => trim(implode("\n", $buffer)),
                ];
            }
            $buffer = [];
        };

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $heading = trim($m[2]);
                if ($docTitle === null && strlen($m[1]) === 1) {
                    // The first H1 is the document title; keep it as the heading
                    // for the lead section rather than spawning an empty section.
                    $docTitle = $heading;
                    $flush();
                    $currentHeading = $heading;
                    continue;
                }
                $flush();
                $currentHeading = $heading;
                continue;
            }
            $buffer[] = $line;
        }
        $flush();

        return $sections;
    }

    // ─── Scoring ───────────────────────────────────────────────────

    /**
     * Score a section against the (already expanded) query terms. Combines
     * term coverage (how many distinct query terms appear) with frequency, and
     * boosts heading matches. Deterministic; range roughly 0..N.
     *
     * @param string[] $queryTerms
     * @param array{heading:string,terms:array<string,int>} $section
     */
    private function scoreSection(array $queryTerms, array $section): float
    {
        $termFreqs = $section['terms'];
        $headingTerms = $this->tokenize($section['heading']);

        $covered = 0;
        $freqScore = 0.0;
        $headingHits = 0;

        foreach (array_unique($queryTerms) as $term) {
            if (isset($termFreqs[$term])) {
                $covered++;
                // Diminishing returns on raw frequency.
                $freqScore += 1.0 + log(1 + $termFreqs[$term]);
            }
            if (in_array($term, $headingTerms, true)) {
                $headingHits++;
            }
        }

        if ($covered === 0) {
            return 0.0;
        }

        // Coverage dominates (rewards a passage that touches many query terms),
        // frequency and heading proximity refine the ordering.
        return ($covered * 2.0) + $freqScore + ($headingHits * 1.5);
    }

    // ─── Text utilities ────────────────────────────────────────────

    /**
     * Expand query tokens via the synonym groups so related phrasings match.
     *
     * @param string[] $tokens
     * @return string[]
     */
    private function expandTerms(array $tokens): array
    {
        $expanded = $tokens;
        foreach (self::SYNONYMS as $group) {
            foreach ($tokens as $t) {
                if (in_array($t, $group, true)) {
                    $expanded = array_merge($expanded, $group);
                    break;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Lower-case, strip punctuation, drop stopwords and very short tokens.
     *
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\-]+/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/', trim($text)) ?: [];

        $stop = $this->stopwords();
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p, '-');
            if ($p === '' || mb_strlen($p) < 3) {
                continue;
            }
            if (isset($stop[$p])) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * Term-frequency map for a block of text.
     *
     * @return array<string,int>
     */
    private function termFrequencies(string $text): array
    {
        $freqs = [];
        foreach ($this->tokenize($text) as $t) {
            $freqs[$t] = ($freqs[$t] ?? 0) + 1;
        }

        return $freqs;
    }

    /** @return array<string,true> */
    private function stopwords(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }
        $words = [
            'the', 'and', 'for', 'are', 'was', 'were', 'you', 'your', 'with', 'that',
            'this', 'have', 'has', 'how', 'what', 'when', 'where', 'which', 'who', 'why',
            'can', 'should', 'would', 'could', 'does', 'did', 'about', 'into', 'from',
            'they', 'their', 'them', 'its', 'it', 'is', 'be', 'been', 'being', 'will',
            'not', 'but', 'all', 'any', 'each', 'per', 'via', 'use', 'used', 'using',
            'do', 'i', 'we', 'a', 'an', 'of', 'in', 'on', 'to', 'or', 'as', 'at', 'by',
            'me', 'my', 'so', 'if', 'run',
        ];
        $set = array_fill_keys($words, true);

        return $set;
    }

    /**
     * Trim a section to a self-contained excerpt at a sentence/word boundary.
     */
    private function trimExcerpt(string $text, int $max = 600): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $lastStop = max(mb_strrpos($cut, '. '), mb_strrpos($cut, '? '), mb_strrpos($cut, '! '));
        if ($lastStop !== false && $lastStop > (int) ($max * 0.5)) {
            return rtrim(mb_substr($cut, 0, $lastStop + 1));
        }
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut) . '...';
    }

    /**
     * Human-readable document title from a relative doc path.
     */
    private function prettyTitle(string $relPath): string
    {
        $name = pathinfo($relPath, PATHINFO_FILENAME);
        $name = preg_replace('/^dp-\d+-/', '', $name) ?? $name;
        $name = str_replace('-', ' ', $name);

        return ucfirst(trim($name));
    }

    private function slugifyAnchor(string $heading): string
    {
        $slug = mb_strtolower($heading);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function relativePath(string $absPath): string
    {
        if (str_starts_with($absPath, $this->basePath . '/')) {
            return substr($absPath, strlen($this->basePath) + 1);
        }

        return $absPath;
    }

    /**
     * Resolve the repo base path, preferring Laravel's base_path() when booted.
     */
    private function resolveBasePath(): string
    {
        if (function_exists('base_path')) {
            try {
                return base_path();
            } catch (\Throwable) {
                // fall through
            }
        }

        // Walk up from this file (packages/ahg-ai-chatbot/src/Services) to the
        // repo root where docs/ lives.
        return dirname(__DIR__, 4);
    }
}
