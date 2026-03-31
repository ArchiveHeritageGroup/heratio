<?php

/**
 * StorageBrowseService - Service for Heratio
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



namespace AhgStorageManage\Services;

use AhgCore\Services\BrowseService;

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
            ->where('physical_object_i18n.culture', $this->culture);
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'type_id' => $row->type_id ?? null,
            'location' => $row->location ?? '',
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
            case 'location':
            case 'locationUp':
                $query->orderBy('physical_object_i18n.location', 'asc');
                break;
            case 'locationDown':
                $query->orderBy('physical_object_i18n.location', 'desc');
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
