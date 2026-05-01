<?php

/**
 * TermBrowseService - Service for Heratio
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



namespace AhgTermTaxonomy\Services;

use AhgCore\Services\BrowseService;
use AhgCore\Traits\WithCultureFallback;
use Illuminate\Support\Facades\DB;

class TermBrowseService extends BrowseService
{
    use WithCultureFallback;

    protected function getTable(): string
    {
        return 'term';
    }

    protected function getI18nTable(): string
    {
        return 'term_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'name';
    }

    protected function getBaseJoins($query)
    {
        // Culture-fallback so terms with only English `term_i18n` show up
        // when the user is browsing in af / xh / zu / etc.
        $this->joinI18nWithFallback($query, 'term_i18n', 'term', aliasPrefix: 'term');

        return $query
            ->join('object', 'term.id', '=', 'object.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            // At least one culture must have a non-empty name.
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('term_cur.name')->where('term_cur.name', '!=', '');
                })->orWhere(function ($qq) {
                    $qq->whereNotNull('term_fb.name')->where('term_fb.name', '!=', '');
                });
            });
    }

    protected function getBaseSelect(): array
    {
        return [
            'term.id',
            DB::raw('COALESCE(term_cur.name, term_fb.name) AS name'),
            'term.taxonomy_id',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getSortOptions(): array
    {
        return [
            'alphabetic' => __('Name'),
            'lastUpdated' => __('Date modified'),
        ];
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        $name = "COALESCE(term_cur.name, term_fb.name)";
        switch ($sort) {
            case 'alphabetic':
                $query->orderByRaw("{$name} {$sortDir}");
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }
        return $query;
    }


    public function browse(array $params): array
    {
        // Apply taxonomy_id filter before parent browse
        $taxonomyId = $params['taxonomy_id'] ?? null;

        if ($taxonomyId) {
            $this->taxonomyId = (int) $taxonomyId;
        }

        return parent::browse($params);
    }

    protected ?int $taxonomyId = null;

    protected function applySearch($query, string $subquery)
    {
        // Apply taxonomy filter
        if ($this->taxonomyId) {
            $query->where('term.taxonomy_id', $this->taxonomyId);
        }

        // Search the COALESCE'd current+fallback name. parent::applySearch
        // would target `term_i18n.name` which doesn't exist any more after
        // we switched to LEFT JOIN cur + LEFT JOIN fb.
        if ($subquery !== '') {
            $query->whereRaw(
                "COALESCE(term_cur.name, term_fb.name) LIKE ?",
                ["%{$subquery}%"]
            );
        }
        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'taxonomy_id' => $row->taxonomy_id ?? null,
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
