<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical hierarchy reads for the closure-table migration (heratio#1333).
 *
 * Read sites that used the nested set (lft/rgt range subtree reads, ancestor
 * lookups, orderBy lft) swap to these methods. Every method is **regression-
 * safe**: it uses the closure / sibling-order sidecar when populated, and
 * falls back to lft/rgt automatically when the closure isn't built for that
 * node (or the tables are missing). So a site can be swapped before every
 * instance has run `ahg:build-closure`, with identical results either way.
 *
 * Descendant scoping uses a closure SUBQUERY (not a materialised PHP array),
 * so it scales to the 322k-node instance.
 */
class HierarchyQueryService
{
    private const CLOSURE = [
        'information_object' => 'information_object_closure',
        'term'               => 'term_closure',
        'menu'               => 'menu_closure',
    ];

    /**
     * heratio#1398 — batch ancestor lookup for the whole set of $nodeIds in a
     * SINGLE query, instead of one ancestorIds() call (2-3 queries + a slow
     * lft/rgt range scan) PER node. This is the N+1 that dominates a full
     * es-reindex; batching it is the biggest per-record win.
     *
     * Fast path: a populated closure table (one indexed IN query).
     * Fallback: a single nested-set self-join over the batch (correct for the
     * one AtoM tree). For very large corpora, build the closure
     * (`ahg:build-closure`) so the fast path applies — the range self-join is
     * O(tree) per node and does not scale to millions.
     *
     * @param  list<int>  $nodeIds
     * @return array<int, list<int>>  nodeId => [ancestorId, …] (immediate parent first)
     */
    public function batchAncestorIds(string $entity, array $nodeIds, bool $includeSelf = false): array
    {
        $nodeIds = array_values(array_unique(array_map('intval', $nodeIds)));
        if (empty($nodeIds)) {
            return [];
        }
        $result = array_fill_keys($nodeIds, []);

        $table = self::CLOSURE[$entity] ?? null;
        $closurePopulated = $table !== null && Schema::hasTable($table)
            && DB::table($table)->whereIn('descendant', $nodeIds)->where('depth', '>', 0)->exists();

        if ($closurePopulated) {
            $rows = DB::table($table)
                ->whereIn('descendant', $nodeIds)
                ->when(! $includeSelf, fn ($q) => $q->where('depth', '>', 0))
                ->orderBy('descendant')
                ->orderByDesc('depth')
                ->get(['descendant', 'ancestor']);
            foreach ($rows as $r) {
                $result[(int) $r->descendant][] = (int) $r->ancestor;
            }

            return $result;
        }

        // Nested-set fallback — ONE self-join for the whole batch (vs a per-node
        // walk). Ancestors are nodes that strictly contain the node's lft/rgt.
        $op1 = $includeSelf ? '<=' : '<';
        $op2 = $includeSelf ? '>=' : '>';
        $rows = DB::table($entity.' as d')
            ->join($entity.' as a', function ($j) use ($op1, $op2) {
                $j->on('a.lft', $op1, 'd.lft')->on('a.rgt', $op2, 'd.rgt');
            })
            ->whereIn('d.id', $nodeIds)
            ->whereNotNull('d.lft')
            ->whereNotNull('d.rgt')
            ->orderBy('d.id')
            ->orderByDesc('a.lft')
            ->get(['d.id as descendant', 'a.id as ancestor']);
        foreach ($rows as $r) {
            $result[(int) $r->descendant][] = (int) $r->ancestor;
        }

        return $result;
    }

    /** Whether the closure for $entity is populated for this ancestor. */
    public function closureReady(string $entity, int $ancestorId): bool
    {
        $table = self::CLOSURE[$entity] ?? null;
        if ($table === null) {
            return false;
        }
        try {
            return Schema::hasTable($table)
                && DB::table($table)->where('ancestor', $ancestorId)->where('depth', '>', 0)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Descendant ids of $ancestorId. Closure when ready, else lft/rgt range.
     *
     * @return list<int>
     */
    public function descendantIds(string $entity, int $ancestorId, bool $includeSelf = false): array
    {
        $table = self::CLOSURE[$entity] ?? null;
        if ($table !== null && $this->closureReady($entity, $ancestorId)) {
            $q = DB::table($table)->where('ancestor', $ancestorId);
            if (! $includeSelf) {
                $q->where('depth', '>', 0);
            }

            return $q->pluck('descendant')->map(fn ($v) => (int) $v)->all();
        }

        return $this->lftRangeIds($entity, $ancestorId, $includeSelf, false);
    }

    /**
     * Ancestor ids of $nodeId (root-most first). Closure when ready, else lft/rgt.
     *
     * @return list<int>
     */
    public function ancestorIds(string $entity, int $nodeId, bool $includeSelf = false): array
    {
        $table = self::CLOSURE[$entity] ?? null;
        if ($table !== null && Schema::hasTable($table)
            && DB::table($table)->where('descendant', $nodeId)->where('depth', '>', 0)->exists()) {
            $q = DB::table($table)->where('descendant', $nodeId);
            if (! $includeSelf) {
                $q->where('depth', '>', 0);
            }

            return $q->orderByDesc('depth')->pluck('ancestor')->map(fn ($v) => (int) $v)->all();
        }

        return $this->lftRangeIds($entity, $nodeId, $includeSelf, true);
    }

    /**
     * Constrain a query to the descendants of $ancestorId via a closure
     * subquery (scales), with an lft/rgt fallback. $keyColumn is the column on
     * the query that holds the entity id (e.g. 'information_object.id').
     */
    public function scopeDescendants(Builder $query, string $entity, int $ancestorId, string $keyColumn, bool $includeSelf = true): Builder
    {
        $table = self::CLOSURE[$entity] ?? null;
        if ($table !== null && $this->closureReady($entity, $ancestorId)) {
            return $query->whereIn($keyColumn, function ($sub) use ($table, $ancestorId, $includeSelf) {
                $sub->select('descendant')->from($table)->where('ancestor', $ancestorId);
                if (! $includeSelf) {
                    $sub->where('depth', '>', 0);
                }
            });
        }

        // Fallback: lft/rgt range; if the node has no nested-set bounds
        // (null lft/rgt), fall back to direct children so `where(lft,>=,null)`
        // can't throw - matching the legacy call sites' own guard.
        $node = $this->nodeBounds($entity, $ancestorId);
        if ($node !== null && $node->lft !== null && $node->rgt !== null) {
            [$lftCol, $rgtCol] = $this->lftRgtColumns($keyColumn);

            return $query->where($lftCol, $includeSelf ? '>=' : '>', $node->lft)
                ->where($rgtCol, $includeSelf ? '<=' : '<', $node->rgt);
        }

        return $query->where($this->qualify($keyColumn, 'parent_id'), $ancestorId);
    }

    /**
     * Order a query by sibling order, falling back to lft. Uses COALESCE so a
     * node missing a sidecar row still sorts by its lft (zero-regression).
     * $keyColumn = the id column on the query; $lftColumn = its lft column.
     */
    public function applySiblingOrder(Builder $query, string $entity, string $keyColumn, string $lftColumn, string $dir = 'asc'): Builder
    {
        if (! Schema::hasTable('ahg_node_sibling_order')) {
            return $query->orderBy($lftColumn, $dir);
        }
        $alias = 'nso_'.substr(md5($keyColumn), 0, 6);
        $query->leftJoin('ahg_node_sibling_order as '.$alias, function ($j) use ($alias, $entity, $keyColumn) {
            $j->on($alias.'.node_id', '=', $keyColumn)->where($alias.'.entity', '=', $entity);
        });

        return $query->orderByRaw("COALESCE({$alias}.sibling_order, {$lftColumn}) ".($dir === 'desc' ? 'desc' : 'asc'));
    }

    // --- fallback helpers --------------------------------------------------

    /** @return list<int> */
    private function lftRangeIds(string $entity, int $id, bool $includeSelf, bool $ancestors): array
    {
        $node = $this->nodeBounds($entity, $id);
        if ($node === null || $node->lft === null || $node->rgt === null) {
            if ($ancestors) {
                return $includeSelf ? [$id] : [];
            }
            $children = DB::table($entity)->where('parent_id', $id)->pluck('id')->map(fn ($v) => (int) $v)->all();

            return $includeSelf ? array_merge([$id], $children) : $children;
        }
        $q = DB::table($entity)->select('id');
        if ($ancestors) {
            $q->where('lft', $includeSelf ? '<=' : '<', $node->lft)
              ->where('rgt', $includeSelf ? '>=' : '>', $node->rgt);
        } else {
            $q->where('lft', $includeSelf ? '>=' : '>', $node->lft)
              ->where('rgt', $includeSelf ? '<=' : '<', $node->rgt);
        }

        return $q->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    private function nodeBounds(string $entity, int $id): ?object
    {
        try {
            return DB::table($entity)->where('id', $id)->select('lft', 'rgt')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Qualify a column with $keyColumn's table prefix ('io.id' -> 'io.parent_id'). */
    private function qualify(string $keyColumn, string $col): string
    {
        $prefix = str_contains($keyColumn, '.') ? substr($keyColumn, 0, strrpos($keyColumn, '.') + 1) : '';

        return $prefix.$col;
    }

    private function lftRgtColumns(string $keyColumn): array
    {
        return [$this->qualify($keyColumn, 'lft'), $this->qualify($keyColumn, 'rgt')];
    }
}
