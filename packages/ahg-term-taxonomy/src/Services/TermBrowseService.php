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

        $query
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

        // #1388: hide terms carrying a restricted community protocol from
        // guests/non-editors (editors/admins bypass inside the gate).
        \AhgCore\Services\TermProtocolGate::addTermVisibilityCriteria($query, 'term.id');

        return $query;
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
        $name = 'COALESCE(term_cur.name, term_fb.name)';
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

        // #743 browseTerm filters: optional parent term scope + "has scope
        // note" toggle, both opt-in via query params. Both filters honour
        // taxonomy_id and apply at applySearch() time so they compose with
        // pagination and sort transparently.
        $this->parentId = isset($params['parent']) && $params['parent'] !== ''
            ? (int) $params['parent']
            : null;
        $this->scopeNoteOnly = ! empty($params['scopeNoteOnly']);

        return parent::browse($params);
    }

    protected ?int $taxonomyId = null;

    protected ?int $parentId = null;

    protected bool $scopeNoteOnly = false;

    protected function applySearch($query, string $subquery)
    {
        // Apply taxonomy filter
        if ($this->taxonomyId) {
            $query->where('term.taxonomy_id', $this->taxonomyId);
        }

        // #743 browseTerm parent filter: restrict to children of a parent
        // term (used by the taxonomy tree-view click-through).
        if ($this->parentId) {
            $query->where('term.parent_id', $this->parentId);
        }

        // #743 browseTerm scope-note filter: only terms with at least one
        // note row attached. Cheap EXISTS check; doesn't pull note content.
        if ($this->scopeNoteOnly) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('note')
                    ->whereColumn('note.object_id', 'term.id');
            });
        }

        // Search the COALESCE'd current+fallback name. parent::applySearch
        // would target `term_i18n.name` which doesn't exist any more after
        // we switched to LEFT JOIN cur + LEFT JOIN fb.
        if ($subquery !== '') {
            $query->whereRaw(
                'COALESCE(term_cur.name, term_fb.name) LIKE ?',
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
