<?php

namespace AhgFunctionManage\Services;

use AhgCore\Services\BrowseService;

class FunctionBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'function_object';
    }

    protected function getI18nTable(): string
    {
        return 'function_object_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'authorized_form_of_name';
    }

    protected function getBaseSelect(): array
    {
        return [
            'function_object.id',
            'function_object_i18n.authorized_form_of_name as name',
            'function_object.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('function_object_i18n', 'function_object.id', '=', 'function_object_i18n.id')
            ->join('object', 'function_object.id', '=', 'object.id')
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('function_object_i18n.culture', $this->culture);
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('function_object_i18n.authorized_form_of_name', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('function_object.description_identifier', $sortDir);
                $query->orderBy('function_object_i18n.authorized_form_of_name', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
