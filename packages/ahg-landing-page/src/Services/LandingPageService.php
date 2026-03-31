<?php

/**
 * LandingPageService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
        return DB::table('ahg_landing_page as p')
            ->leftJoin(DB::raw('(SELECT page_id, COUNT(*) as block_count FROM ahg_landing_block GROUP BY page_id) as bc'), 'p.id', '=', 'bc.page_id')
            ->select('p.*', DB::raw('COALESCE(bc.block_count, 0) as block_count'))
            ->orderBy('p.name')
            ->get();
    }

    public function getPage(int $id): ?object
    {
        return DB::table('ahg_landing_page')->where('id', $id)->first();
    }

    public function getPageBySlug(?string $slug): ?object
    {
        if ($slug) {
            return DB::table('ahg_landing_page')
                ->where('slug', $slug)
                ->where('is_active', 1)
                ->first();
        }

        return DB::table('ahg_landing_page')
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();
    }

    public function getPageBlocks(int $pageId, bool $visibleOnly = true): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_landing_block as b')
            ->leftJoin('ahg_landing_block_type as bt', 'b.block_type_id', '=', 'bt.id')
            ->where('b.page_id', $pageId)
            ->whereNull('b.parent_block_id')
            ->select('b.*', 'bt.label as type_label', 'bt.icon as type_icon', 'bt.machine_name',
                'bt.config_schema', 'bt.default_config');

        if ($visibleOnly) {
            $query->where('b.is_visible', 1);
        }

        return $query->orderBy('b.position')->get();
    }

    public function getBlockTypes(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_landing_block_type')->orderBy('label')->get();
    }

    public function createPage(array $data, int $userId): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $exists = DB::table('ahg_landing_page')->where('slug', $data['slug'])->exists();
        if ($exists) {
            return ['success' => false, 'error' => 'Slug already exists'];
        }

        $data['created_by'] = $userId;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('ahg_landing_page')->insertGetId($data);

        return ['success' => true, 'page_id' => $id];
    }

    public function updatePage(int $id, array $data, int $userId): array
    {
        $data['updated_at'] = now();
        DB::table('ahg_landing_page')->where('id', $id)->update($data);

        return ['success' => true];
    }

    public function deletePage(int $id, int $userId): array
    {
        DB::table('ahg_landing_block')->where('page_id', $id)->delete();
        DB::table('ahg_landing_page')->where('id', $id)->delete();

        return ['success' => true];
    }

    public function addBlock(int $pageId, int $blockTypeId, array $config, int $userId, array $options = []): array
    {
        $maxPos = DB::table('ahg_landing_block')
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

        if (!empty($options['parent_block_id'])) {
            $data['parent_block_id'] = $options['parent_block_id'];
            $data['column_slot'] = $options['column_slot'] ?? null;
        }

        $id = DB::table('ahg_landing_block')->insertGetId($data);

        return ['success' => true, 'block_id' => $id];
    }

    public function updateBlock(int $blockId, array $data, int $userId): array
    {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }

        $data['updated_at'] = now();
        DB::table('ahg_landing_block')->where('id', $blockId)->update($data);

        return ['success' => true];
    }

    public function deleteBlock(int $blockId, int $userId): array
    {
        DB::table('ahg_landing_block')->where('parent_block_id', $blockId)->delete();
        DB::table('ahg_landing_block')->where('id', $blockId)->delete();

        return ['success' => true];
    }

    public function reorderBlocks(int $pageId, array $order, int $userId): array
    {
        foreach ($order as $item) {
            DB::table('ahg_landing_block')
                ->where('id', $item['id'])
                ->where('page_id', $pageId)
                ->update(['position' => $item['position']]);
        }

        return ['success' => true];
    }

    public function duplicateBlock(int $blockId, int $userId): array
    {
        $block = DB::table('ahg_landing_block')->where('id', $blockId)->first();

        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        $newId = DB::table('ahg_landing_block')->insertGetId([
            'page_id' => $block->page_id,
            'block_type_id' => $block->block_type_id,
            'config' => $block->config,
            'title' => ($block->title ?? '') . ' (copy)',
            'position' => $block->position + 1,
            'is_visible' => $block->is_visible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['success' => true, 'block_id' => $newId];
    }

    public function toggleBlockVisibility(int $blockId, int $userId): array
    {
        $block = DB::table('ahg_landing_block')->where('id', $blockId)->first();

        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        DB::table('ahg_landing_block')->where('id', $blockId)->update([
            'is_visible' => $block->is_visible ? 0 : 1,
            'updated_at' => now(),
        ]);

        return ['success' => true, 'is_visible' => !$block->is_visible];
    }

    public function getPageVersions(int $pageId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_landing_page_version')
            ->where('page_id', $pageId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function getUserDashboards(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_landing_page')
            ->where('created_by', $userId)
            ->where('page_type', 'dashboard')
            ->orderBy('name')
            ->get();
    }
}
