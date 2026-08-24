<?php

/**
 * LandingPageService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLandingPage\Services;

use Illuminate\Support\Facades\DB;

class LandingPageService
{
    public function getAllPages(): \Illuminate\Support\Collection
    {
        return DB::table('atom_landing_page as p')
            ->leftJoin(DB::raw('(SELECT page_id, COUNT(*) as block_count FROM atom_landing_page_block GROUP BY page_id) as bc'), 'p.id', '=', 'bc.page_id')
            ->select('p.*', DB::raw('COALESCE(bc.block_count, 0) as block_count'))
            ->orderBy('p.name')
            ->get();
    }

    public function getPage(int $id): ?object
    {
        return DB::table('atom_landing_page')->where('id', $id)->first();
    }

    public function getPageBySlug(?string $slug): ?object
    {
        if ($slug) {
            return DB::table('atom_landing_page')
                ->where('slug', $slug)
                ->where('is_active', 1)
                ->first();
        }

        return DB::table('atom_landing_page')
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();
    }

    public function getPageBlocks(int $pageId, bool $visibleOnly = true): \Illuminate\Support\Collection
    {
        $query = DB::table('atom_landing_page_block as b')
            ->leftJoin('atom_landing_page_block_type as bt', 'b.block_type_id', '=', 'bt.id')
            ->where('b.page_id', $pageId)
            ->whereNull('b.parent_block_id')
            ->select('b.*', 'bt.label as type_label', 'bt.icon as type_icon', 'bt.machine_name',
                'bt.config_schema', 'bt.default_config');

        if ($visibleOnly) {
            $query->where('b.is_visible', 1);
        }

        $blocks = $query->orderBy('b.position')->get();

        // #1478: the row/column block templates read $block->child_blocks and
        // nothing ever attached them, so a row block - whose entire purpose is
        // to hold children - rendered an empty row on every page. Children are
        // fetched in one query and grouped, rather than one query per row.
        return $this->attachChildBlocks($blocks, $pageId, $visibleOnly);
    }

    /**
     * Attach each block's children, keyed by column_slot order then position.
     *
     * @param  \Illuminate\Support\Collection<int,object>  $blocks
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function attachChildBlocks(
        \Illuminate\Support\Collection $blocks,
        int $pageId,
        bool $visibleOnly
    ): \Illuminate\Support\Collection {
        if ($blocks->isEmpty()) {
            return $blocks;
        }

        $childQuery = DB::table('atom_landing_page_block as b')
            ->leftJoin('atom_landing_page_block_type as bt', 'b.block_type_id', '=', 'bt.id')
            ->where('b.page_id', $pageId)
            ->whereIn('b.parent_block_id', $blocks->pluck('id')->all())
            ->select('b.*', 'bt.label as type_label', 'bt.icon as type_icon', 'bt.machine_name',
                'bt.config_schema', 'bt.default_config');

        if ($visibleOnly) {
            $childQuery->where('b.is_visible', 1);
        }

        $children = $childQuery
            ->orderBy('b.column_slot')
            ->orderBy('b.position')
            ->get()
            ->groupBy('parent_block_id');

        foreach ($blocks as $block) {
            $block->child_blocks = $children->get($block->id, collect())->values();
        }

        return $blocks;
    }

    public function getBlockTypes(): \Illuminate\Support\Collection
    {
        return DB::table('atom_landing_page_block_type')->orderBy('label')->get();
    }

    public function createPage(array $data, int $userId): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $exists = DB::table('atom_landing_page')->where('slug', $data['slug'])->exists();
        if ($exists) {
            return ['success' => false, 'error' => 'Slug already exists'];
        }

        // AtoM `atom_landing_page` uses `user_id` as the owner column.
        $data['user_id'] = $userId;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('atom_landing_page')->insertGetId($data);

        return ['success' => true, 'page_id' => $id];
    }

    public function updatePage(int $id, array $data, int $userId): array
    {
        $data['updated_at'] = now();
        DB::table('atom_landing_page')->where('id', $id)->update($data);

        return ['success' => true];
    }

    public function deletePage(int $id, int $userId): array
    {
        DB::table('atom_landing_page_block')->where('page_id', $id)->delete();
        DB::table('atom_landing_page')->where('id', $id)->delete();

        return ['success' => true];
    }

    public function addBlock(int $pageId, int $blockTypeId, array $config, int $userId, array $options = []): array
    {
        $maxPos = DB::table('atom_landing_page_block')
            ->where('page_id', $pageId)
            ->max('position') ?? 0;

        $data = [
            'page_id' => $pageId,
            'block_type_id' => $blockTypeId,
            'config' => json_encode($config),
            'position' => $maxPos + 1,
            'is_visible' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (! empty($options['parent_block_id'])) {
            $data['parent_block_id'] = $options['parent_block_id'];
            $data['column_slot'] = $options['column_slot'] ?? null;
        }

        $id = DB::table('atom_landing_page_block')->insertGetId($data);

        return ['success' => true, 'block_id' => $id];
    }

    public function updateBlock(int $blockId, array $data, int $userId): array
    {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }

        $data['updated_at'] = now();
        DB::table('atom_landing_page_block')->where('id', $blockId)->update($data);

        return ['success' => true];
    }

    public function deleteBlock(int $blockId, int $userId): array
    {
        DB::table('atom_landing_page_block')->where('parent_block_id', $blockId)->delete();
        DB::table('atom_landing_page_block')->where('id', $blockId)->delete();

        return ['success' => true];
    }

    public function reorderBlocks(int $pageId, array $order, int $userId): array
    {
        foreach ($order as $item) {
            DB::table('atom_landing_page_block')
                ->where('id', $item['id'])
                ->where('page_id', $pageId)
                ->update(['position' => $item['position']]);
        }

        return ['success' => true];
    }

    public function duplicateBlock(int $blockId, int $userId): array
    {
        $block = DB::table('atom_landing_page_block')->where('id', $blockId)->first();

        if (! $block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        $newId = DB::table('atom_landing_page_block')->insertGetId([
            'page_id' => $block->page_id,
            'block_type_id' => $block->block_type_id,
            'config' => $block->config,
            'title' => ($block->title ?? '').' (copy)',
            'position' => $block->position + 1,
            'is_visible' => $block->is_visible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['success' => true, 'block_id' => $newId];
    }

    public function toggleBlockVisibility(int $blockId, int $userId): array
    {
        $block = DB::table('atom_landing_page_block')->where('id', $blockId)->first();

        if (! $block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        DB::table('atom_landing_page_block')->where('id', $blockId)->update([
            'is_visible' => $block->is_visible ? 0 : 1,
            'updated_at' => now(),
        ]);

        return ['success' => true, 'is_visible' => ! $block->is_visible];
    }

    public function getPageVersions(int $pageId): \Illuminate\Support\Collection
    {
        return DB::table('atom_landing_page_version')
            ->where('page_id', $pageId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function getUserDashboards(int $userId): \Illuminate\Support\Collection
    {
        // AtoM has no `page_type` column; dashboards are modeled as pages
        // whose `layout` field is set to 'dashboard'.
        return DB::table('atom_landing_page')
            ->where('user_id', $userId)
            ->where('layout', 'dashboard')
            ->orderBy('name')
            ->get();
    }
}
