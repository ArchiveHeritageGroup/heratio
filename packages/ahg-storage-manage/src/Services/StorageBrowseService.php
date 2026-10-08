<?php

/**
 * StorageBrowseService - Service for Heratio
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

namespace AhgStorageManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StorageBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'physical_object';
    }

    protected function getI18nTable(): string
    {
        return 'physical_object_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'name';
    }

    protected function getBaseSelect(): array
    {
        return [
            'physical_object.id',
            'physical_object_i18n.name as name',
            'physical_object.type_id',
            'physical_object_i18n.location',
            // heratio#1545 - the flat location fields, for the Location column of
            // a box not yet in the storage location tree.
            'poe.building', 'poe.floor', 'poe.room', 'poe.aisle', 'poe.bay', 'poe.rack', 'poe.shelf',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->join('object', 'physical_object.id', '=', 'object.id')
            ->join('slug', 'physical_object.id', '=', 'slug.object_id')
            ->leftJoin('physical_object_extended as poe', 'poe.physical_object_id', '=', 'physical_object.id')
            ->where('physical_object_i18n.culture', $this->culture);
    }

    /** heratio#1545 - search covers the name, the old free text, building, room and shelf. */
    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $like = '%'.addcslashes($subquery, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('physical_object_i18n.name', 'LIKE', $like)
                    ->orWhere('physical_object_i18n.location', 'LIKE', $like)
                    ->orWhere('poe.building', 'LIKE', $like)
                    ->orWhere('poe.room', 'LIKE', $like)
                    ->orWhere('poe.shelf', 'LIKE', $like);
            });
        }

        return $query;
    }

    /**
     * The page of results, each with its place in the storage location tree
     * (heratio#1545): two queries for the page, not one per row.
     */
    public function browse(array $params): array
    {
        $result = parent::browse($params);

        $paths = $this->treePaths(array_map(static fn ($hit) => (int) $hit['id'], $result['hits']));
        foreach ($result['hits'] as &$hit) {
            $hit['tree_path'] = $paths[(int) $hit['id']] ?? [];
        }
        unset($hit);

        return $result;
    }

    /**
     * Each object's place in the tree, outermost first.
     *
     * @param  int[]  $objectIds
     * @return array<int, array<int, array{id: int, name: string, slug: string}>>
     */
    protected function treePaths(array $objectIds): array
    {
        if (! $objectIds || ! Schema::hasTable('ahg_physical_object_location')) {
            return [];
        }

        $placed = DB::table('ahg_physical_object_location')->whereIn('physical_object_id', $objectIds)
            ->pluck('location_id', 'physical_object_id')->all();
        if (! $placed) {
            return [];
        }

        $steps = [];
        foreach (DB::table('ahg_storage_location_closure as c')
            ->join('ahg_storage_location as l', 'l.id', '=', 'c.ancestor')
            ->whereIn('c.descendant', array_unique(array_values($placed)))
            ->orderBy('c.depth', 'desc')
            ->get(['c.descendant', 'l.id', 'l.name', 'l.slug']) as $row) {
            $steps[(int) $row->descendant][] = ['id' => (int) $row->id, 'name' => (string) $row->name, 'slug' => (string) $row->slug];
        }

        $paths = [];
        foreach ($placed as $objectId => $locationId) {
            $paths[(int) $objectId] = $steps[(int) $locationId] ?? [];
        }

        return $paths;
    }

    /** The flat location fields as one line, as the object's own page shows them. */
    protected static function flatPath(object $row): string
    {
        $parts = [];
        foreach (['building' => '', 'floor' => 'Floor ', 'room' => 'Room ', 'aisle' => 'Aisle ', 'bay' => 'Bay ', 'rack' => 'Rack ', 'shelf' => 'Shelf '] as $field => $prefix) {
            $value = trim((string) ($row->{$field} ?? ''));
            if ($value !== '') {
                $parts[] = ($prefix === '' || stripos($value, trim($prefix)) === 0) ? $value : $prefix.$value;
            }
        }

        return implode(' > ', $parts);
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'type_id' => $row->type_id ?? null,
            'location' => $row->location ?? '',
            'flat_path' => self::flatPath($row),
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'lastUpdated':
                $query->orderBy('object.updated_at', $sortDir);
                break;
            // heratio#1545 - empty places last, then building, floor, room, then
            // the old free text. $sortDir is whitelisted to asc|desc by the base.
            case 'location':
            case 'locationUp':
            case 'locationDown':
                $dir = $sort === 'locationDown' ? 'desc' : ($sort === 'location' ? $sortDir : 'asc');
                $query->orderByRaw("COALESCE(poe.building, '') = '' asc")
                    ->orderBy('poe.building', $dir)->orderBy('poe.floor', $dir)->orderBy('poe.room', $dir)
                    ->orderBy('physical_object_i18n.location', $dir);
                break;
            case 'nameDown':
                $query->orderBy('physical_object_i18n.name', 'desc');
                break;
            case 'alphabetic':
            case 'nameUp':
            default:
                $query->orderBy('physical_object_i18n.name', $sortDir);
                break;
        }

        return $query;
    }
}
