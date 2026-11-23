<?php

/**
 * AuthorityHierarchyService - relational broader/narrower terms and
 * hierarchy-based search expansion (heratio#1475 item 3).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AuthorityHierarchyService
{
    public const ORIGIN_LOCAL = 'local';
    public const ORIGIN_SYNC = 'sync';
    public const EXPANSION_DEPTH = 3;
    public const EXPANSION_LIMIT = 200;

    public function broader(int $id): array
    {
        return $this->neighbours($id, 'narrower_id', 'broader_id');
    }

    public function narrower(int $id): array
    {
        return $this->neighbours($id, 'broader_id', 'narrower_id');
    }

    private function neighbours(int $id, string $from, string $to): array
    {
        return DB::table('library_authority_relation as r')
            ->join('library_subject_authority as a', 'a.id', '=', 'r.'.$to)
            ->where('r.'.$from, $id)
            ->where('r.suppressed', 0)
            ->orderBy('a.heading')
            ->get(['r.id as relation_id', 'r.origin', 'a.id', 'a.heading'])
            ->all();
    }

    /** @return int[] every term above $id, nearest first */
    public function ancestors(int $id, ?int $maxDepth = null): array
    {
        return $this->walk($id, 'narrower_id', 'broader_id', $maxDepth);
    }

    /** @return int[] every term below $id, nearest first */
    public function descendants(int $id, ?int $maxDepth = null, ?int $limit = null): array
    {
        return $this->walk($id, 'broader_id', 'narrower_id', $maxDepth, $limit);
    }

    /** Breadth-first; the visited set makes it safe on cyclic data. */
    private function walk(int $seed, string $from, string $to, ?int $maxDepth, ?int $limit = null): array
    {
        $seen = [$seed => true];
        $found = [];
        $frontier = [$seed];

        for ($depth = 0; $frontier && ($maxDepth === null || $depth < $maxDepth); $depth++) {
            $next = [];
            foreach (array_chunk($frontier, 500) as $chunk) {
                $ids = DB::table('library_authority_relation')
                    ->whereIn($from, $chunk)->where('suppressed', 0)->pluck($to);
                foreach ($ids as $rawId) {
                    $nodeId = (int) $rawId;
                    if (isset($seen[$nodeId])) {
                        continue;
                    }
                    $seen[$nodeId] = true;
                    $found[] = $nodeId;
                    $next[] = $nodeId;
                    if ($limit !== null && count($found) >= $limit) {
                        return $found;
                    }
                }
            }
            $frontier = $next;
        }

        return $found;
    }

    /**
     * @return bool true when the relation is active afterwards. A sync never
     *              revives a relation staff removed.
     * @throws InvalidArgumentException on a self-link, unknown term or cycle
     */
    public function addRelation(int $broaderId, int $narrowerId, string $origin = self::ORIGIN_LOCAL): bool
    {
        if ($broaderId === $narrowerId) {
            throw new InvalidArgumentException('A term cannot be broader than itself.');
        }
        if (DB::table('library_subject_authority')->whereIn('id', [$broaderId, $narrowerId])->count() !== 2) {
            throw new InvalidArgumentException('Both terms must be existing authority records.');
        }

        $existing = DB::table('library_authority_relation')
            ->where('broader_id', $broaderId)->where('narrower_id', $narrowerId)->first();

        if ($existing && $existing->suppressed && $origin === self::ORIGIN_SYNC) {
            return false;
        }
        if ($existing && ! $existing->suppressed) {
            if ($origin === self::ORIGIN_LOCAL && $existing->origin !== self::ORIGIN_LOCAL) {
                DB::table('library_authority_relation')->where('id', $existing->id)
                    ->update(['origin' => self::ORIGIN_LOCAL, 'updated_at' => now()]);
            }

            return true;
        }

        if (in_array($broaderId, $this->descendants($narrowerId), true)) {
            throw new InvalidArgumentException('That relation would create a loop in the hierarchy.');
        }

        if ($existing) {
            DB::table('library_authority_relation')->where('id', $existing->id)
                ->update(['suppressed' => 0, 'origin' => $origin, 'updated_at' => now()]);
        } else {
            DB::table('library_authority_relation')->insert([
                'broader_id' => $broaderId, 'narrower_id' => $narrowerId, 'origin' => $origin,
                'suppressed' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return true;
    }

    /** Suppressed, never deleted: the row is what keeps a vocabulary from re-adding it. */
    public function removeRelation(int $relationId): ?object
    {
        $relation = DB::table('library_authority_relation')->where('id', $relationId)->first();
        if (! $relation) {
            return null;
        }

        DB::table('library_authority_relation')->where('id', $relationId)
            ->update(['suppressed' => 1, 'updated_at' => now()]);

        return $relation;
    }

    /** Resolves a typed reference: a numeric id, or an exact heading if unambiguous. */
    public function resolveReference(string $reference): ?int
    {
        $reference = trim($reference);
        if (ctype_digit($reference)) {
            $id = DB::table('library_subject_authority')->where('id', (int) $reference)->value('id');

            return $id ? (int) $id : null;
        }

        $ids = $this->matchHeading($reference);

        return count($ids) === 1 ? $ids[0] : null;
    }

    /** @return int[] */
    private function matchHeading(string $term): array
    {
        $normalized = AuthorityControlService::normalizeHeading($term);
        if ($normalized === '') {
            return [];
        }

        $query = DB::table('library_subject_authority');
        Schema::hasColumn('library_subject_authority', 'heading_normalized')
            ? $query->where('heading_normalized', $normalized)
            : $query->whereRaw('LOWER(heading) = ?', [$normalized]);

        return $query->limit(20)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Search expansion: a query that exactly names an authority also finds its
     * narrower terms, a bounded number of levels down.
     *
     * @return array{ids:int[],headings:string[]}
     */
    public function expandForSearch(string $term, int $depth = self::EXPANSION_DEPTH): array
    {
        if (! Schema::hasTable('library_authority_relation')) {
            return ['ids' => [], 'headings' => []];
        }

        $ids = $this->matchHeading($term);
        foreach ($ids as $id) {
            $remaining = self::EXPANSION_LIMIT - count($ids);
            if ($remaining <= 0) {
                break;
            }
            $ids = array_values(array_unique(array_merge($ids, $this->descendants($id, $depth, $remaining))));
        }

        if (! $ids) {
            return ['ids' => [], 'headings' => []];
        }

        return [
            'ids' => $ids,
            'headings' => DB::table('library_subject_authority')->whereIn('id', $ids)
                ->pluck('heading')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Converts the legacy flat broader_terms / narrower_terms JSON into relations.
     *
     * @return array{linked:int,unresolved:int,rejected:int}
     */
    public function backfillFlatTerms(bool $dryRun = false): array
    {
        $totals = ['linked' => 0, 'unresolved' => 0, 'rejected' => 0];
        if (! Schema::hasColumn('library_subject_authority', 'broader_terms')) {
            return $totals;
        }

        DB::table('library_subject_authority')
            ->where(fn ($q) => $q->whereNotNull('broader_terms')->orWhereNotNull('narrower_terms'))
            ->select('id', 'broader_terms', 'narrower_terms')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$totals, $dryRun) {
                foreach ($rows as $row) {
                    foreach (['broader_terms' => true, 'narrower_terms' => false] as $column => $isBroader) {
                        foreach ($this->flatLabels($row->{$column}) as $label) {
                            $otherId = $this->resolveReferenceByHeading($label);
                            if (! $otherId) {
                                $totals['unresolved']++;
                                continue;
                            }
                            [$broaderId, $narrowerId] = $isBroader ? [$otherId, (int) $row->id] : [(int) $row->id, $otherId];
                            if (DB::table('library_authority_relation')->where('broader_id', $broaderId)
                                ->where('narrower_id', $narrowerId)->where('suppressed', 1)->exists()) {
                                $totals['rejected']++; // staff removed it
                                continue;
                            }
                            if ($dryRun) {
                                $totals['linked']++;
                                continue;
                            }
                            try {
                                $this->addRelation($broaderId, $narrowerId);
                                $totals['linked']++;
                            } catch (InvalidArgumentException) {
                                $totals['rejected']++;
                            }
                        }
                    }
                }
            });

        return $totals;
    }

    private function resolveReferenceByHeading(string $label): ?int
    {
        $ids = $this->matchHeading($label);

        return count($ids) === 1 ? $ids[0] : null;
    }

    /** @return string[] */
    private function flatLabels(?string $json): array
    {
        $items = json_decode((string) $json, true);
        if (! is_array($items)) {
            return [];
        }

        $labels = [];
        foreach ($items as $item) {
            $label = is_array($item) ? ($item['label'] ?? $item['heading'] ?? $item['term'] ?? null) : $item;
            if (is_string($label) && trim($label) !== '') {
                $labels[] = trim($label);
            }
        }

        return $labels;
    }
}
