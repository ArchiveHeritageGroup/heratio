<?php

/**
 * Heratio - SKOS cross-vocabulary match service.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Manages the `ahg_term_cross_match` table that links Heratio terms to
 * concepts in external vocabularies (LCSH, Getty AAT, Wikidata, ...) via
 * SKOS mapping predicates. The SKOS exporter in TermController reads from
 * here to emit skos:exactMatch / closeMatch / broadMatch / narrowMatch /
 * relatedMatch triples across all four serialisations.
 *
 * #661 Phase 3.
 */

namespace AhgTermTaxonomy\Services;

use Illuminate\Support\Facades\DB;

class CrossMatchService
{
    /**
     * Maximum ids per whereIn. MySQL's prepared-statement limit is 65,535
     * placeholders; staying well under it leaves room for the other bindings
     * in a query and keeps each statement cheap to plan.
     */
    public const ID_CHUNK = 5000;

    public const MATCH_TYPES = [
        'exactMatch',
        'closeMatch',
        'broadMatch',
        'narrowMatch',
        'relatedMatch',
    ];

    public const SOURCES = [
        'manual',
        'getty',
        'loc',
        'wikidata',
        'automated',
    ];

    /**
     * Return all cross-vocab matches for a term, ordered for stable display.
     *
     * @return array<int, object>
     */
    public function forTerm(int $termId): array
    {
        return DB::table('ahg_term_cross_match')
            ->where('term_id', $termId)
            ->orderBy('match_type')
            ->orderBy('target_label')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Bulk-fetch matches keyed by term_id for use by the SKOS exporter
     * (avoids N+1 across the concept walk).
     *
     * @param  array<int, int>  $termIds
     * @return array<int, array<int, object>>
     */
    public function forTerms(array $termIds): array
    {
        if (empty($termIds)) {
            return [];
        }

        // Chunked because MySQL caps a prepared statement at 65,535
        // placeholders. A whole-taxonomy id list can exceed that outright -
        // atom.theahg.co.za's Places taxonomy holds 196,322 terms - and the
        // statement then fails before it runs, rather than being slow (#1424).
        $rows = collect();
        foreach (array_chunk($termIds, self::ID_CHUNK) as $chunk) {
            $rows = $rows->concat(
                DB::table('ahg_term_cross_match')
                    ->whereIn('term_id', $chunk)
                    ->orderBy('match_type')
                    ->orderBy('target_label')
                    ->orderBy('id')
                    ->get()
            );
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->term_id][] = $r;
        }

        return $out;
    }

    /**
     * Create a new cross-vocab match. Returns the new row id.
     */
    public function create(int $termId, string $matchType, string $targetUri, array $opts = []): int
    {
        $matchType = $this->normaliseMatchType($matchType);
        $source = $this->normaliseSource($opts['source'] ?? 'manual');
        $confidence = isset($opts['confidence']) ? (float) $opts['confidence'] : null;
        if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
            throw new \InvalidArgumentException('confidence must be between 0 and 1');
        }
        if (! filter_var($targetUri, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('target_uri must be a valid URL');
        }

        return (int) DB::table('ahg_term_cross_match')->insertGetId([
            'term_id' => $termId,
            'match_type' => $matchType,
            'target_uri' => $targetUri,
            'target_label' => $opts['target_label'] ?? null,
            'target_vocab' => $opts['target_vocab'] ?? null,
            'confidence' => $confidence,
            'source' => $source,
            'created_at' => now(),
        ]);
    }

    /**
     * Delete a single match by id (and term, for scoping safety).
     */
    public function delete(int $termId, int $matchId): int
    {
        return DB::table('ahg_term_cross_match')
            ->where('id', $matchId)
            ->where('term_id', $termId)
            ->delete();
    }

    /**
     * Replace the full set of matches for a term in one shot. Used by the
     * term-edit form which submits all rows as a single array.
     *
     * Each row in $rows: ['match_type'=>..., 'target_uri'=>..., 'target_label'?, 'target_vocab'?, 'confidence'?, 'source'?]
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceAll(int $termId, array $rows): int
    {
        DB::table('ahg_term_cross_match')->where('term_id', $termId)->delete();
        $written = 0;
        foreach ($rows as $r) {
            $uri = trim((string) ($r['target_uri'] ?? ''));
            if ($uri === '') {
                continue;
            }
            try {
                $this->create(
                    $termId,
                    (string) ($r['match_type'] ?? 'exactMatch'),
                    $uri,
                    [
                        'target_label' => $r['target_label'] ?? null,
                        'target_vocab' => $r['target_vocab'] ?? null,
                        'confidence' => $r['confidence'] ?? null,
                        'source' => $r['source'] ?? 'manual',
                    ]
                );
                $written++;
            } catch (\InvalidArgumentException $e) {
                // Skip invalid rows silently; caller has request-validation upstream.
                continue;
            }
        }

        return $written;
    }

    private function normaliseMatchType(string $t): string
    {
        $t = trim($t);
        if (! in_array($t, self::MATCH_TYPES, true)) {
            throw new \InvalidArgumentException(
                'match_type must be one of: '.implode(', ', self::MATCH_TYPES)
            );
        }

        return $t;
    }

    private function normaliseSource(string $s): string
    {
        $s = trim($s);
        if (! in_array($s, self::SOURCES, true)) {
            return 'manual';
        }

        return $s;
    }
}
