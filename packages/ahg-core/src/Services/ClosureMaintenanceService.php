<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Incremental closure-table + sibling-order maintenance (heratio#1333).
 *
 * Replaces the O(n) lft/rgt renumber with O(subtree) closure writes. Every
 * mutation is set-based and wrapped in a transaction so the tree can never be
 * left half-updated (the failure mode the nested set suffers from). The caller
 * inserts/updates/deletes the base row; this keeps the closure + the
 * ahg_node_sibling_order sidecar consistent with parent_id.
 *
 * Entity is one of information_object | term | menu. Methods no-op safely if the
 * closure infrastructure is not installed yet (mid-migration / fresh DB).
 */
class ClosureMaintenanceService
{
    private const CLOSURE = [
        'information_object' => 'information_object_closure',
        'term'               => 'term_closure',
        'menu'               => 'menu_closure',
    ];

    /**
     * Register a NEW node (no existing descendants) under $parentId.
     *
     * Closure: self row (X,X,0) + one row per ancestor of the parent, depth+1.
     * Sibling order: appended after the parent's current children unless an
     * explicit $order is given.
     */
    public function addNode(string $entity, int $nodeId, ?int $parentId, ?int $order = null): void
    {
        $closure = $this->closure($entity);
        if ($closure === null) {
            return;
        }

        DB::transaction(function () use ($entity, $closure, $nodeId, $parentId, $order) {
            // Idempotent: clear any stale rows for this node first.
            DB::table($closure)->where('descendant', $nodeId)->delete();

            DB::table($closure)->insert(['ancestor' => $nodeId, 'descendant' => $nodeId, 'depth' => 0]);

            if ($parentId !== null) {
                DB::insert(
                    "INSERT INTO `{$closure}` (ancestor, descendant, depth)
                     SELECT ancestor, ?, depth + 1 FROM `{$closure}` WHERE descendant = ?",
                    [$nodeId, $parentId]
                );
            }

            $this->writeSiblingOrder($entity, $nodeId, $parentId, $order);
        });
    }

    /**
     * Move an existing node (and its whole subtree) under $newParentId
     * (null = make it a root). Canonical closure subtree move: detach the
     * subtree from its old ancestors, then reattach under the new parent.
     */
    public function moveNode(string $entity, int $nodeId, ?int $newParentId, ?int $order = null): void
    {
        $closure = $this->closure($entity);
        if ($closure === null) {
            return;
        }

        DB::transaction(function () use ($entity, $closure, $nodeId, $newParentId, $order) {
            // 1. Disconnect the subtree from every strict ancestor of the node:
            //    delete (A, D) where A is a strict ancestor of the node and D is
            //    in the node's subtree. The subtree's internal closure stays.
            DB::delete(
                "DELETE FROM `{$closure}`
                 WHERE descendant IN (SELECT d FROM (SELECT descendant AS d FROM `{$closure}` WHERE ancestor = ?) AS sub)
                   AND ancestor   IN (SELECT a FROM (SELECT ancestor   AS a FROM `{$closure}` WHERE descendant = ? AND ancestor <> ?) AS sup)",
                [$nodeId, $nodeId, $nodeId]
            );

            // 2. Reattach under the new parent (skip if it becomes a root).
            if ($newParentId !== null) {
                DB::insert(
                    "INSERT INTO `{$closure}` (ancestor, descendant, depth)
                     SELECT super.ancestor, sub.descendant, super.depth + sub.depth + 1
                     FROM `{$closure}` super
                     JOIN `{$closure}` sub ON sub.ancestor = ?
                     WHERE super.descendant = ?",
                    [$nodeId, $newParentId]
                );
            }

            $this->writeSiblingOrder($entity, $nodeId, $newParentId, $order);
        });
    }

    /**
     * Remove a node's sibling-order row. Closure rows are removed automatically
     * by the ON DELETE CASCADE FK when the base row is deleted; this also clears
     * them explicitly in case the base row is deleted in a way that bypasses the
     * FK (e.g. a soft path) or the caller wants closure gone first.
     */
    public function removeNode(string $entity, int $nodeId): void
    {
        $closure = $this->closure($entity);
        if ($closure !== null) {
            DB::table($closure)->where('descendant', $nodeId)->delete();
        }
        if (Schema::hasTable('ahg_node_sibling_order')) {
            DB::table('ahg_node_sibling_order')->where('entity', $entity)->where('node_id', $nodeId)->delete();
        }
    }

    /**
     * Re-derive sibling order for one parent's children from the current lft
     * order. Used by sibling-reorder operations (same parent => closure is
     * unchanged, only the order moves). During the dual-write phase lft is still
     * authoritative, so seeding sibling_order from it keeps the two consistent.
     */
    public function resyncSiblingOrder(string $entity, ?int $parentId): void
    {
        if ($this->closure($entity) === null || ! Schema::hasTable('ahg_node_sibling_order')) {
            return;
        }
        $ids = DB::table($entity)
            ->where('parent_id', $parentId)
            ->orderBy('lft')->orderBy('id')
            ->pluck('id');
        $order = 0;
        foreach ($ids as $id) {
            DB::table('ahg_node_sibling_order')->updateOrInsert(
                ['entity' => $entity, 'node_id' => (int) $id],
                ['parent_id' => $parentId, 'sibling_order' => $order++]
            );
        }
    }

    // --- internals --------------------------------------------------------

    private function writeSiblingOrder(string $entity, int $nodeId, ?int $parentId, ?int $order): void
    {
        if (! Schema::hasTable('ahg_node_sibling_order')) {
            return;
        }
        if ($order === null) {
            $max = DB::table('ahg_node_sibling_order')
                ->where('entity', $entity)
                ->where('parent_id', $parentId)
                ->where('node_id', '<>', $nodeId)
                ->max('sibling_order');
            $order = $max === null ? 0 : ((int) $max + 1);
        }
        DB::table('ahg_node_sibling_order')->updateOrInsert(
            ['entity' => $entity, 'node_id' => $nodeId],
            ['parent_id' => $parentId, 'sibling_order' => $order]
        );
    }

    private function closure(string $entity): ?string
    {
        $table = self::CLOSURE[$entity] ?? null;
        if ($table === null) {
            return null;
        }

        return Schema::hasTable($table) ? $table : null;
    }
}
