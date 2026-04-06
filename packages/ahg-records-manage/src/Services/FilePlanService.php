<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FilePlanService
{
    /**
     * Get tree structure starting from optional parent, recursive.
     */
    public function getTree(?int $parentId = null): array
    {
        if (!Schema::hasTable('rm_fileplan_node')) {
            return [];
        }

        $query = DB::table('rm_fileplan_node');
        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $nodes = $query->orderBy('code')->get();
        $tree = [];

        foreach ($nodes as $node) {
            $item = (array) $node;
            $item['children'] = $this->getTree($node->id);
            $item['record_count'] = $this->getRecordCountForNode($node->id);
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * Get all nodes ordered by lft for flat display.
     */
    public function getTreeFlat(): array
    {
        if (!Schema::hasTable('rm_fileplan_node')) {
            return [];
        }

        return DB::table('rm_fileplan_node')
            ->orderByRaw('COALESCE(lft, 999999)')
            ->orderBy('code')
            ->get()
            ->map(function ($node) {
                $node->record_count = $this->getRecordCountForNode($node->id);
                return $node;
            })
            ->toArray();
    }

    /**
     * Get a single node with parent info, disposal class info, and record count.
     */
    public function getNode(int $id): ?object
    {
        if (!Schema::hasTable('rm_fileplan_node')) {
            return null;
        }

        $node = DB::table('rm_fileplan_node as n')
            ->leftJoin('rm_fileplan_node as p', 'n.parent_id', '=', 'p.id')
            ->select(
                'n.*',
                'p.code as parent_code',
                'p.title as parent_title'
            )
            ->where('n.id', $id)
            ->first();

        if (!$node) {
            return null;
        }

        if ($node->disposal_class_id && Schema::hasTable('rm_disposal_class')) {
            $disposalClass = DB::table('rm_disposal_class')
                ->where('id', $node->disposal_class_id)
                ->first();
            $node->disposal_class_code = $disposalClass->code ?? null;
            $node->disposal_class_title = $disposalClass->title ?? null;
        } else {
            $node->disposal_class_code = null;
            $node->disposal_class_title = null;
        }

        $node->record_count = $this->getRecordCountForNode($id);
        $node->child_count = DB::table('rm_fileplan_node')->where('parent_id', $id)->count();

        return $node;
    }

    /**
     * Create a new file plan node.
     */
    public function createNode(array $data): int
    {
        $id = DB::table('rm_fileplan_node')->insertGetId([
            'parent_id' => $data['parent_id'] ?? null,
            'function_object_id' => $data['function_object_id'] ?? null,
            'node_type' => $data['node_type'] ?? 'series',
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'disposal_class_id' => $data['disposal_class_id'] ?? null,
            'retention_period' => $data['retention_period'] ?? null,
            'disposal_action' => $data['disposal_action'] ?? null,
            'status' => $data['status'] ?? 'active',
            'source_department' => $data['source_department'] ?? null,
            'source_agency_code' => $data['source_agency_code'] ?? null,
            'import_session_id' => $data['import_session_id'] ?? null,
            'depth' => $data['depth'] ?? 0,
            'created_by' => $data['created_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->rebuildNestedSet();

        return $id;
    }

    /**
     * Update an existing file plan node.
     */
    public function updateNode(int $id, array $data): bool
    {
        $updateData = [];

        $fillable = [
            'parent_id', 'function_object_id', 'node_type', 'code', 'title',
            'description', 'disposal_class_id', 'retention_period', 'disposal_action',
            'status', 'source_department', 'source_agency_code',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['updated_at'] = now();

        $result = DB::table('rm_fileplan_node')
            ->where('id', $id)
            ->update($updateData);

        return $result >= 0;
    }

    /**
     * Delete a node only if it has no children and no linked records.
     */
    public function deleteNode(int $id): bool
    {
        $childCount = DB::table('rm_fileplan_node')->where('parent_id', $id)->count();
        if ($childCount > 0) {
            return false;
        }

        $recordCount = $this->getRecordCountForNode($id);
        if ($recordCount > 0) {
            return false;
        }

        $deleted = DB::table('rm_fileplan_node')->where('id', $id)->delete();

        if ($deleted) {
            $this->rebuildNestedSet();
        }

        return $deleted > 0;
    }

    /**
     * Move a node to a new parent.
     */
    public function moveNode(int $nodeId, int $newParentId): bool
    {
        $node = DB::table('rm_fileplan_node')->where('id', $nodeId)->first();
        $parent = DB::table('rm_fileplan_node')->where('id', $newParentId)->first();

        if (!$node || !$parent) {
            return false;
        }

        // Prevent moving a node into its own subtree
        if ($parent->lft !== null && $node->lft !== null) {
            if ($parent->lft >= $node->lft && $parent->rgt <= $node->rgt) {
                return false;
            }
        }

        DB::table('rm_fileplan_node')
            ->where('id', $nodeId)
            ->update([
                'parent_id' => $newParentId,
                'updated_at' => now(),
            ]);

        $this->rebuildNestedSet();

        return true;
    }

    /**
     * Get records linked to a file plan node.
     */
    public function getRecordsInNode(int $nodeId, int $page = 1, int $perPage = 25): array
    {
        $node = DB::table('rm_fileplan_node')->where('id', $nodeId)->first();
        if (!$node) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', 'en');
            });

        // Link via rm_record_disposal_class if available
        if (Schema::hasTable('rm_record_disposal_class') && $node->disposal_class_id) {
            $query->join('rm_record_disposal_class as rdc', function ($join) use ($node) {
                $join->on('io.id', '=', 'rdc.information_object_id')
                    ->where('rdc.disposal_class_id', '=', $node->disposal_class_id);
            });
        } else {
            // Fallback: match IO identifier to file plan code
            $query->where('io.identifier', 'LIKE', $node->code . '%');
        }

        $total = $query->count();

        $data = $query
            ->select('io.id', 'io.identifier', 'i18n.title', 'io.created_at')
            ->orderBy('io.identifier')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Rebuild the nested set (lft/rgt/depth) for all nodes.
     */
    public function rebuildNestedSet(?int $rootId = null): void
    {
        $counter = 0;

        // Get root nodes (those with no parent)
        $roots = DB::table('rm_fileplan_node')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        foreach ($roots as $root) {
            $lft = ++$counter;
            $this->rebuildNestedSetRecursive($root->id, $counter, 1);
            $rgt = ++$counter;
            DB::table('rm_fileplan_node')
                ->where('id', $root->id)
                ->update(['lft' => $lft, 'rgt' => $rgt, 'depth' => 0]);
        }
    }

    private function rebuildNestedSetRecursive(int $parentId, int &$counter, int $depth): void
    {
        $children = DB::table('rm_fileplan_node')
            ->where('parent_id', $parentId)
            ->orderBy('code')
            ->get();

        foreach ($children as $child) {
            $lft = ++$counter;
            $this->rebuildNestedSetRecursive($child->id, $counter, $depth + 1);
            $rgt = ++$counter;
            DB::table('rm_fileplan_node')
                ->where('id', $child->id)
                ->update(['lft' => $lft, 'rgt' => $rgt, 'depth' => $depth]);
        }
    }

    /**
     * Get a node by its code.
     */
    public function getNodeByCode(string $code): ?object
    {
        return DB::table('rm_fileplan_node')
            ->where('code', $code)
            ->first();
    }

    /**
     * Get statistics about the file plan.
     */
    public function getStats(): array
    {
        if (!Schema::hasTable('rm_fileplan_node')) {
            return [
                'total_nodes' => 0,
                'by_type' => [],
                'by_department' => [],
                'by_status' => [],
                'linked_records' => 0,
            ];
        }

        $totalNodes = DB::table('rm_fileplan_node')->count();

        $byType = DB::table('rm_fileplan_node')
            ->selectRaw('node_type, COUNT(*) as cnt')
            ->groupBy('node_type')
            ->pluck('cnt', 'node_type')
            ->toArray();

        $byDepartment = DB::table('rm_fileplan_node')
            ->selectRaw('COALESCE(source_department, \'(none)\') as dept, COUNT(*) as cnt')
            ->groupBy('source_department')
            ->pluck('cnt', 'dept')
            ->toArray();

        $byStatus = DB::table('rm_fileplan_node')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $linkedRecords = 0;
        if (Schema::hasTable('rm_record_disposal_class')) {
            $linkedRecords = DB::table('rm_record_disposal_class')
                ->distinct('information_object_id')
                ->count('information_object_id');
        }

        return [
            'total_nodes' => $totalNodes,
            'by_type' => $byType,
            'by_department' => $byDepartment,
            'by_status' => $byStatus,
            'linked_records' => $linkedRecords,
        ];
    }

    /**
     * Get breadcrumb path from root to given node.
     */
    public function getBreadcrumb(int $nodeId): array
    {
        $crumbs = [];
        $current = DB::table('rm_fileplan_node')->where('id', $nodeId)->first();

        while ($current) {
            array_unshift($crumbs, $current);
            if ($current->parent_id) {
                $current = DB::table('rm_fileplan_node')->where('id', $current->parent_id)->first();
            } else {
                break;
            }
        }

        return $crumbs;
    }

    /**
     * Get child nodes of a given parent.
     */
    public function getChildren(int $parentId): array
    {
        return DB::table('rm_fileplan_node')
            ->where('parent_id', $parentId)
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get all nodes as a flat list for dropdowns.
     */
    public function getNodesForDropdown(): array
    {
        return DB::table('rm_fileplan_node')
            ->orderByRaw('COALESCE(lft, 999999)')
            ->orderBy('code')
            ->select('id', 'code', 'title', 'depth')
            ->get()
            ->toArray();
    }

    /**
     * Count records linked to a node via identifier matching or disposal class.
     */
    private function getRecordCountForNode(int $nodeId): int
    {
        $node = DB::table('rm_fileplan_node')->where('id', $nodeId)->first();
        if (!$node) {
            return 0;
        }

        if (Schema::hasTable('rm_record_disposal_class') && $node->disposal_class_id) {
            return DB::table('rm_record_disposal_class')
                ->where('disposal_class_id', $node->disposal_class_id)
                ->count();
        }

        return DB::table('information_object')
            ->where('identifier', 'LIKE', $node->code . '%')
            ->count();
    }
}
