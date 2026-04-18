<?php

/**
 * AccessionBrowseService - Service for Heratio
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



namespace AhgAccessionManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class AccessionBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'accession';
    }

    protected function getI18nTable(): string
    {
        return 'accession_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'title';
    }

    protected function getBaseSelect(): array
    {
        return [
            'accession.id',
            'accession_i18n.title as name',
            'accession.identifier',
            'accession.date as accession_date',
            'accession.processing_status_id',
            'accession.processing_priority_id',
            'accession.acquisition_type_id',
            'accession.resource_type_id',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('object', 'accession.id', '=', 'object.id')
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession_i18n.culture', $this->culture);
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('accession_i18n.title', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('accession.identifier', $sortDir);
                $query->orderBy('accession_i18n.title', $sortDir);
                break;
            case 'date':
                $query->orderBy('accession.date', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $query->where(function ($q) use ($subquery) {
                $q->where('accession_i18n.title', 'LIKE', "%{$subquery}%")
                  ->orWhere('accession.identifier', 'LIKE', "%{$subquery}%");
            });
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'accession_date' => $row->accession_date ?? '',
            'processing_status_id' => $row->processing_status_id ?? null,
            'processing_priority_id' => $row->processing_priority_id ?? null,
            'acquisition_type_id' => $row->acquisition_type_id ?? null,
            'resource_type_id' => $row->resource_type_id ?? null,
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }

    /**
     * Resolve term names for status and priority IDs.
     */
    public function resolveTermNames(array $hits): array
    {
        $ids = [];
        foreach ($hits as $h) {
            if (!empty($h['processing_status_id'])) $ids[] = $h['processing_status_id'];
            if (!empty($h['processing_priority_id'])) $ids[] = $h['processing_priority_id'];
            if (!empty($h['acquisition_type_id'])) $ids[] = $h['acquisition_type_id'];
            if (!empty($h['resource_type_id'])) $ids[] = $h['resource_type_id'];
        }
        $ids = array_unique(array_filter($ids));
        if (empty($ids)) return [];

        return DB::table('term_i18n')
            ->whereIn('id', $ids)
            ->where('culture', $this->culture)
            ->pluck('name', 'id')
            ->toArray();
    }
}
