<?php

/**
 * FunctionBrowseService - Service for Heratio
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
            'function_object.type_id',
            'function_object_i18n.classification',
            'function_object_i18n.dates',
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
            'type_id' => $row->type_id ?? null,
            'classification' => $row->classification ?? '',
            'dates' => $row->dates ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
