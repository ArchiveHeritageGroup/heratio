<?php

/**
 * DescriptionGridService - spreadsheet-style grid editing of descriptions (heratio#1581).
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

namespace AhgInformationObjectManage\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\HierarchyQueryService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Reads a page of descriptions for the grid and saves one row at a time.
 *
 * A row save goes through InformationObjectService::update(), the service
 * the API and sector forms use, so the audit entry, the "last modified"
 * fingerprint, the scope-and-content sanitiser and the translation rule
 * (a field equal to the source text is not stored for another language)
 * apply exactly as there. Nothing here writes a description table itself.
 */
class DescriptionGridService
{
    public const PER_PAGE = 50;

    public const MAX_IDS = 500;

    /** Shown until the user picks their own columns. */
    public const DEFAULT_COLUMNS = ['identifier', 'title', 'level_of_description_id', 'extent_and_medium', 'scope_and_content'];

    /** Columns that hold long text and edit in a textarea. */
    public const LONG_TEXT = [
        'scope_and_content', 'archival_history', 'acquisition', 'appraisal', 'accruals', 'arrangement',
        'physical_characteristics', 'finding_aids', 'location_of_originals', 'location_of_copies',
        'related_units_of_description', 'rules', 'sources', 'revision_history',
    ];

    /**
     * Grid column => label. Identifier, level, and the ISAD text fields a
     * translation can carry (the two encrypted access fields are left out).
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        $labels = [
            'identifier' => __('Identifier'),
            'title' => __('Title'),
            'level_of_description_id' => __('Level of description'),
            'alternate_title' => __('Alternate title'),
            'edition' => __('Edition'),
            'extent_and_medium' => __('Extent and medium'),
            'archival_history' => __('Archival history'),
            'acquisition' => __('Immediate source of acquisition or transfer'),
            'scope_and_content' => __('Scope and content'),
            'appraisal' => __('Appraisal, destruction and scheduling'),
            'accruals' => __('Accruals'),
            'arrangement' => __('System of arrangement'),
            'physical_characteristics' => __('Physical characteristics and technical requirements'),
            'finding_aids' => __('Finding aids'),
            'location_of_originals' => __('Existence and location of originals'),
            'location_of_copies' => __('Existence and location of copies'),
            'related_units_of_description' => __('Related units of description'),
            'institution_responsible_identifier' => __('Institution identifier'),
            'rules' => __('Rules or conventions'),
            'sources' => __('Sources'),
            'revision_history' => __('Dates of creation revision deletion'),
        ];
        $allowed = array_merge(['identifier', 'level_of_description_id'], InformationObjectService::translatableFields());

        return array_intersect_key($labels, array_flip($allowed));
    }

    /** @return array<int, string> level term id => name, in the current language */
    public function levels(): array
    {
        $culture = app()->getLocale();

        return DB::table('term')
            ->leftJoin('term_i18n as c', function ($j) use ($culture) {
                $j->on('c.id', '=', 'term.id')->where('c.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as s', function ($j) {
                $j->on('s.id', '=', 'term.id')->on('s.culture', '=', 'term.source_culture');
            })
            ->where('term.taxonomy_id', InformationObjectService::TAXONOMY_LEVELS_OF_DESCRIPTION)
            ->orderBy('term.lft')
            ->get(['term.id', DB::raw('COALESCE(c.name, s.name) as name')])
            ->mapWithKeys(fn ($t) => [(int) $t->id => (string) $t->name])
            ->all();
    }

    /**
     * One page of rows. $scope is ['collection' => id] (the record and all
     * beneath it, in tree order) or ['ids' => [...]] (a browse selection, in
     * the order given). Text shows in the current language, else the
     * record's own language, as the edit form pre-fills it.
     */
    public function page(array $scope, int $page, ?int $userId): LengthAwarePaginator
    {
        $culture = app()->getLocale();
        $fields = InformationObjectService::translatableFields();

        $q = DB::table('information_object as io')
            ->join('object as o', 'o.id', '=', 'io.id')
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as c', function ($j) use ($culture) {
                $j->on('c.id', '=', 'io.id')->where('c.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as s', function ($j) {
                $j->on('s.id', '=', 'io.id')->on('s.culture', '=', 'io.source_culture');
            })
            ->where('io.id', '>', 1);

        if (! empty($scope['collection'])) {
            app(HierarchyQueryService::class)->scopeDescendants($q, 'information_object', (int) $scope['collection'], 'io.id', true);
            $q->orderByRaw('io.lft IS NULL')->orderBy('io.lft')->orderBy('io.id');
        } else {
            $ids = array_slice(array_values(array_unique(array_map('intval', $scope['ids'] ?? []))), 0, self::MAX_IDS);
            $q->whereIn('io.id', $ids ?: [0]);
            if ($ids) {
                $q->orderByRaw('FIELD(io.id, '.implode(',', $ids).')');
            }
        }

        $select = ['io.id', 'io.identifier', 'io.level_of_description_id', 'io.source_culture', 'o.updated_at', 'sl.slug'];
        foreach ($fields as $f) {
            $select[] = DB::raw("COALESCE(c.{$f}, s.{$f}) as {$f}");
        }

        $paginator = $q->select($select)->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));
        $paginator->getCollection()->transform(function ($row) use ($userId) {
            $id = (int) $row->id;
            $row->readable = $this->odrlAllows($id, $userId);
            $row->editable = $row->readable
                && AclService::hasPermission($userId, 'update', $id)
                && ! \AhgCore\Http\Middleware\DeclaredRecordLock::isDeclared($id);
            if (! $row->readable) {
                // Withheld by a rights policy: the row shows only that it exists.
                foreach (InformationObjectService::translatableFields() as $f) {
                    $row->{$f} = null;
                }
            }

            return $row;
        });

        return $paginator;
    }

    /**
     * Save one row. $fields holds only the cells the user changed. Refused
     * (409) when the record changed after the grid loaded it.
     *
     * @return array{ok:bool, status:int, error?:string, errors?:array, updated_at?:string}
     */
    public function saveRow(int $id, string $loadedUpdatedAt, array $fields, ?int $userId, ?string $culture = null): array
    {
        $culture = $culture ?: app()->getLocale();
        $current = DB::table('information_object as io')->join('object as o', 'o.id', '=', 'io.id')
            ->where('io.id', $id)->where('io.id', '>', 1)->value('o.updated_at');
        if ($current === null) {
            return ['ok' => false, 'status' => 404, 'error' => __('That description was not found.')];
        }
        if (! AclService::hasPermission($userId, 'update', $id) || ! $this->odrlAllows($id, $userId)) {
            return ['ok' => false, 'status' => 403, 'error' => __('You do not have permission to edit this record.')];
        }
        if (\AhgCore\Http\Middleware\DeclaredRecordLock::isDeclared($id)) {
            return ['ok' => false, 'status' => 423, 'error' => __('This is a declared record: its description and files are read-only.')];
        }
        if ((string) $current !== $loadedUpdatedAt) {
            return ['ok' => false, 'status' => 409, 'updated_at' => (string) $current,
                'error' => __('This record was changed by someone else after the grid loaded it. Reload the page to see the new version; your changes to this row were not saved.')];
        }

        $unknown = array_diff(array_keys($fields), array_keys($this->columns()));
        if ($unknown) {
            return ['ok' => false, 'status' => 422, 'error' => __('Unknown column: :c', ['c' => implode(', ', $unknown)])];
        }
        if (! $fields) {
            return ['ok' => true, 'status' => 200, 'updated_at' => (string) $current];
        }

        $rules = ['identifier' => 'nullable|string|max:1024', 'title' => 'sometimes|required|string|max:65535',
            'level_of_description_id' => ['nullable', 'integer', function ($attr, $value, $fail) {
                if ($value !== null && $value !== '' && ! DB::table('term')->where('id', (int) $value)
                    ->where('taxonomy_id', InformationObjectService::TAXONOMY_LEVELS_OF_DESCRIPTION)->exists()) {
                    $fail(__('That level of description does not exist.'));
                }
            }]];
        foreach (InformationObjectService::translatableFields() as $f) {
            $rules[$f] = $rules[$f] ?? 'nullable|string|max:65535';
        }
        $validator = Validator::make($fields, array_intersect_key($rules, $fields), [], $this->columns());
        if ($validator->fails()) {
            return ['ok' => false, 'status' => 422, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        try {
            InformationObjectService::update($id, $validator->validated(), $culture);
        } catch (\DomainException $e) {
            return ['ok' => false, 'status' => 422, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'status' => 200,
            'updated_at' => (string) DB::table('object')->where('id', $id)->value('updated_at')];
    }

    /** ODRL "use" policies, as on the record page: administrators bypass them. */
    private function odrlAllows(int $id, ?int $userId): bool
    {
        if (! class_exists(\AhgResearch\Services\OdrlService::class) || ! Schema::hasTable('research_rights_policy')) {
            return true;
        }
        if ($userId && in_array(\AhgCore\Models\AclGroup::ADMINISTRATOR_ID, array_map('intval', AclService::getUserGroupIds($userId)), true)) {
            return true;
        }
        $researcherId = $userId && Schema::hasTable('research_researcher')
            ? DB::table('research_researcher')->where('user_id', $userId)->value('id') : null;

        return app(\AhgResearch\Services\OdrlService::class)
            ->isPermitted('archival_description', $id, $researcherId ? (int) $researcherId : null, 'use');
    }
}
