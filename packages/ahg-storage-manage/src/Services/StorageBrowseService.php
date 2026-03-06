<?php

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
            default:
                $query->orderBy('physical_object_i18n.name', $sortDir);
                break;
        }

        return $query;
    }
}
