<?php

/**
 * LibraryService - Service for Heratio
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



namespace AhgLibrary\Services;

use AhgCore\Traits\WithCultureFallback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LibraryService
{
    use WithCultureFallback;

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Get a library item by its slug, joining information_object + i18n + slug + library_item + display_object_config.
     */
    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return null;
        }

        return $this->getById($objectId);
    }

    /**
     * Get a library item by ID with all joined data.
     */
    public function getById(int $id): ?object
    {
        // Culture-fallback i18n joins via WithCultureFallback trait.
        return DB::table('information_object')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->tap(fn ($q) => $this->joinI18nWithFallback($q, 'information_object_i18n', 'information_object', aliasPrefix: 'ioi'))
            ->leftJoin('library_item', 'information_object.id', '=', 'library_item.information_object_id')
            ->leftJoin('display_object_config', function ($j) {
                $j->on('information_object.id', '=', 'display_object_config.object_id')
                    ->where('display_object_config.object_type', '=', 'library');
            })
            ->where('information_object.id', $id)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.source_culture',
                'information_object.icip_sensitivity',
                DB::raw('COALESCE(ioi_cur.title, ioi_fb.title) AS title'),
                DB::raw('COALESCE(ioi_cur.scope_and_content, ioi_fb.scope_and_content) AS scope_and_content'),
                'information_object.source_culture as language',
                'library_item.id as library_item_id',
                'library_item.material_type',
                'library_item.subtitle',
                'library_item.responsibility_statement',
                'library_item.call_number',
                'library_item.classification_scheme',
                'library_item.classification_number',
                'library_item.dewey_decimal',
                'library_item.cutter_number',
                'library_item.shelf_location',
                'library_item.copy_number',
                'library_item.volume_designation',
                'library_item.isbn',
                'library_item.issn',
                'library_item.lccn',
                'library_item.oclc_number',
                'library_item.openlibrary_id',
                'library_item.goodreads_id',
                'library_item.librarything_id',
                'library_item.openlibrary_url',
                'library_item.ebook_preview_url',
                'library_item.cover_url',
                'library_item.cover_url_original',
                'library_item.doi',
                'library_item.barcode',
                'library_item.edition',
                'library_item.edition_statement',
                'library_item.publisher',
                'library_item.publication_place',
                'library_item.publication_date',
                'library_item.series_title',
                'library_item.series_number',
                'library_item.pagination',
                'library_item.dimensions',
                'library_item.physical_details',
                'library_item.summary',
                'library_item.contents_note',
                'library_item.general_note',
                'library_item.bibliography_note',
                'library_item.created_at as library_created_at',
                'library_item.updated_at as library_updated_at',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Browse library items with pagination, filtering, and search.
     */
    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 10)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'lastUpdated';
        $sortDir = !empty($params['sortDir']) ? $params['sortDir'] : (($sort === 'lastUpdated') ? 'desc' : 'asc');
        $subquery = trim($params['subquery'] ?? '');
        $materialType = trim($params['material_type'] ?? '');

        try {
            $query = DB::table('library_item')
                ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
                ->join('object', 'information_object.id', '=', 'object.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->leftJoin('information_object_i18n', function ($j) {
                    $j->on('information_object.id', '=', 'information_object_i18n.id')
                        ->where('information_object_i18n.culture', '=', $this->culture);
                });

            // Filter by material type
            if ($materialType !== '') {
                $query->where('library_item.material_type', $materialType);
            }

            // Search on title, isbn, publisher, responsibility_statement (author)
            if ($subquery !== '') {
                $query->where(function ($q) use ($subquery) {
                    $q->where('information_object_i18n.title', 'LIKE', "%{$subquery}%")
                      ->orWhere('library_item.isbn', 'LIKE', "%{$subquery}%")
                      ->orWhere('library_item.issn', 'LIKE', "%{$subquery}%")
                      ->orWhere('library_item.publisher', 'LIKE', "%{$subquery}%")
                      ->orWhere('library_item.responsibility_statement', 'LIKE', "%{$subquery}%");
                });
            }

            $query->select([
                'information_object.id',
                'information_object_i18n.title as name',
                'library_item.material_type',
                'library_item.isbn',
                'library_item.publisher',
                'library_item.responsibility_statement',
                'library_item.cover_url',
                'library_item.call_number',
                'object.updated_at',
                'slug.slug',
            ]);

            $total = $query->count();

            // Sorting
            switch ($sort) {
                case 'alphabetic':
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'identifier':
                    $query->orderBy('information_object.identifier', $sortDir);
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'materialType':
                    $query->orderBy('library_item.material_type', $sortDir);
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'lastUpdated':
                default:
                    $query->orderBy('object.updated_at', $sortDir);
                    break;
            }

            $rows = $query->skip($skip)->take($limit)->get();

            $hits = [];
            foreach ($rows as $row) {
                $hits[] = [
                    'id' => $row->id,
                    'name' => $row->name ?? '',
                    'material_type' => $row->material_type ?? '',
                    'isbn' => $row->isbn ?? '',
                    'publisher' => $row->publisher ?? '',
                    'responsibility_statement' => $row->responsibility_statement ?? '',
                    'cover_url' => $row->cover_url ?? '',
                    'call_number' => $row->call_number ?? '',
                    'updated_at' => $row->updated_at ?? '',
                    'slug' => $row->slug ?? '',
                ];
            }

            return [
                'hits' => $hits,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Exception $e) {
            \Log::error('LibraryService browse error: ' . $e->getMessage());

            return [
                'hits' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
            ];
        }
    }

    /**
     * Create a new library item with information_object + i18n + slug + library_item + display_object_config.
     *
     * @return int The new information_object ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // 2. Create information_object record
            $ioInsert = [
                'id' => $id,
                'identifier' => $data['identifier'] ?? null,
                'level_of_description_id' => $data['level_of_description_id'] ?? null,
                'repository_id' => $data['repository_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? 1,
                'lft' => 0,
                'rgt' => 0,
                'source_culture' => $this->culture,
            ];
            if (array_key_exists('icip_sensitivity', $data) && $data['icip_sensitivity'] !== '') {
                $ioInsert['icip_sensitivity'] = $data['icip_sensitivity'];
            }
            DB::table('information_object')->insert($ioInsert);

            // 3. Create information_object_i18n record
            DB::table('information_object_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'title' => $data['title'] ?? null,
                'scope_and_content' => $data['scope_and_content'] ?? null,
            ]);

            // 4. Generate and insert slug
            $baseSlug = Str::slug($data['title'] ?? $data['identifier'] ?? 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            DB::table('slug')->insert([
                'object_id' => $id,
                'slug' => $slug,
            ]);

            // 5. Create library_item record
            $libraryItemId = DB::table('library_item')->insertGetId([
                'information_object_id' => $id,
                'material_type' => $data['material_type'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'responsibility_statement' => $data['responsibility_statement'] ?? null,
                'call_number' => $data['call_number'] ?? null,
                'classification_scheme' => $data['classification_scheme'] ?? null,
                'classification_number' => $data['classification_number'] ?? null,
                'dewey_decimal' => $data['dewey_decimal'] ?? null,
                'cutter_number' => $data['cutter_number'] ?? null,
                'shelf_location' => $data['shelf_location'] ?? null,
                'copy_number' => $data['copy_number'] ?? null,
                'volume_designation' => $data['volume_designation'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'issn' => $data['issn'] ?? null,
                'lccn' => $data['lccn'] ?? null,
                'oclc_number' => $data['oclc_number'] ?? null,
                'openlibrary_id' => $data['openlibrary_id'] ?? null,
                'goodreads_id' => $data['goodreads_id'] ?? null,
                'librarything_id' => $data['librarything_id'] ?? null,
                'openlibrary_url' => $data['openlibrary_url'] ?? null,
                'ebook_preview_url' => $data['ebook_preview_url'] ?? null,
                'cover_url' => $data['cover_url'] ?? null,
                'cover_url_original' => $data['cover_url_original'] ?? null,
                'doi' => $data['doi'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'edition' => $data['edition'] ?? null,
                'edition_statement' => $data['edition_statement'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'publication_place' => $data['publication_place'] ?? null,
                'publication_date' => $data['publication_date'] ?? null,
                'series_title' => $data['series_title'] ?? null,
                'series_number' => $data['series_number'] ?? null,
                'pagination' => $data['pagination'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'physical_details' => $data['physical_details'] ?? null,
                'contents_note' => $data['contents_note'] ?? null,
                'general_note' => $data['general_note'] ?? null,
                'bibliography_note' => $data['bibliography_note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Sync creators / subjects / item physical-location if submitted
            if (array_key_exists('creators', $data)) {
                $this->syncCreators((int) $libraryItemId, (array) $data['creators']);
            }
            if (array_key_exists('subjects', $data)) {
                $this->syncSubjects((int) $libraryItemId, (array) $data['subjects']);
            }
            if (array_key_exists('itemLocation', $data)) {
                $this->upsertItemLocation($id, $data['itemLocation']);
            }

            // 7. Create display_object_config record
            DB::table('display_object_config')->insert([
                'object_id' => $id,
                'object_type' => 'library',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });
    }

    /**
     * Update an existing library item.
     */
    public function update(string $slug, array $data): void
    {
        $item = $this->getBySlug($slug);
        if (!$item) {
            return;
        }

        $id = $item->id;

        DB::transaction(function () use ($id, $data) {
            // 1. Update information_object
            $ioUpdate = [];
            $ioFields = ['identifier', 'level_of_description_id', 'repository_id', 'icip_sensitivity'];
            foreach ($ioFields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    if ($field === 'icip_sensitivity' && $value === '') {
                        $value = null;
                    }
                    $ioUpdate[$field] = $value;
                }
            }
            if (!empty($ioUpdate)) {
                DB::table('information_object')->where('id', $id)->update($ioUpdate);
            }

            // 2. Update information_object_i18n (upsert)
            // Issue #61 Phase 3c: snapshot before, run update, detect overrides.
            $i18nFields = ['title', 'scope_and_content'];
            $i18nData = [];
            foreach ($i18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $i18nData[$field] = $data[$field];
                }
            }
            if (!empty($i18nData)) {
                $beforeI18n = (array) (DB::table('information_object_i18n')
                    ->where('id', $id)->where('culture', $this->culture)
                    ->first(array_keys($i18nData)) ?? []);
                $exists = DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('information_object_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18nData);
                } else {
                    DB::table('information_object_i18n')->insert(array_merge(
                        ['id' => $id, 'culture' => $this->culture],
                        $i18nData
                    ));
                }
                try {
                    app(\AhgProvenanceAi\Services\OverrideService::class)
                        ->detectOverridesFromForm('information_object', (int) $id, $beforeI18n, $i18nData, (int) (auth()->id() ?? 0));
                } catch (\Throwable $e) { \Log::warning('LibraryService update: override detection failed: ' . $e->getMessage()); }
            }

            // 3. Update library_item (upsert)
            $libraryFields = [
                'material_type', 'subtitle', 'responsibility_statement',
                'call_number', 'classification_scheme', 'classification_number',
                'dewey_decimal', 'cutter_number', 'shelf_location', 'copy_number',
                'volume_designation', 'isbn', 'issn', 'lccn', 'oclc_number',
                'openlibrary_id', 'goodreads_id', 'librarything_id', 'openlibrary_url',
                'ebook_preview_url', 'cover_url', 'cover_url_original', 'doi', 'barcode',
                'edition', 'edition_statement', 'publisher', 'publication_place',
                'publication_date', 'series_title', 'series_number', 'pagination',
                'dimensions', 'physical_details', 'contents_note',
                'general_note', 'bibliography_note',
            ];
            $libraryData = [];
            foreach ($libraryFields as $field) {
                if (array_key_exists($field, $data)) {
                    $libraryData[$field] = $data[$field];
                }
            }
            if (!empty($libraryData)) {
                $libraryData['updated_at'] = now();
                $exists = DB::table('library_item')
                    ->where('information_object_id', $id)
                    ->exists();

                if ($exists) {
                    DB::table('library_item')
                        ->where('information_object_id', $id)
                        ->update($libraryData);
                } else {
                    DB::table('library_item')->insert(array_merge(
                        ['information_object_id' => $id, 'created_at' => now()],
                        $libraryData
                    ));
                }
            }

            // 4. Sync creators + subjects (replace-all on save) and upsert
            // item physical-location row.
            if (array_key_exists('creators', $data) || array_key_exists('subjects', $data)) {
                $libraryItemId = (int) DB::table('library_item')
                    ->where('information_object_id', $id)
                    ->value('id');
                if ($libraryItemId) {
                    if (array_key_exists('creators', $data)) {
                        $this->syncCreators($libraryItemId, (array) $data['creators']);
                    }
                    if (array_key_exists('subjects', $data)) {
                        $this->syncSubjects($libraryItemId, (array) $data['subjects']);
                    }
                }
            }
            if (array_key_exists('itemLocation', $data)) {
                $this->upsertItemLocation($id, $data['itemLocation']);
            }

            // 5. Touch the object record
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Replace all library_item_creator rows for the given library_item with
     * the supplied list. Empty `name` rows are dropped; sort_order tracks the
     * form-submission order; `role` defaults to 'author'.
     */
    private function syncCreators(int $libraryItemId, array $creators): void
    {
        DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->delete();

        $sort = 0;
        foreach ($creators as $c) {
            if (!is_array($c)) continue;
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') continue;
            $role = trim((string) ($c['role'] ?? '')) ?: 'author';
            $uri  = trim((string) ($c['authority_uri'] ?? ''));
            DB::table('library_item_creator')->insert([
                'library_item_id' => $libraryItemId,
                'name'            => $name,
                'role'            => $role,
                'authority_uri'   => $uri !== '' ? $uri : null,
                'sort_order'      => $sort++,
                'created_at'      => now(),
            ]);
        }
    }

    /**
     * Replace all library_item_subject rows for the given library_item with
     * the supplied list. Empty `heading` rows are dropped; subject_type
     * defaults to 'topic'.
     */
    private function syncSubjects(int $libraryItemId, array $subjects): void
    {
        DB::table('library_item_subject')
            ->where('library_item_id', $libraryItemId)
            ->delete();

        foreach ($subjects as $s) {
            if (!is_array($s)) continue;
            $heading = trim((string) ($s['heading'] ?? ''));
            if ($heading === '') continue;
            DB::table('library_item_subject')->insert([
                'library_item_id' => $libraryItemId,
                'heading'         => $heading,
                'subject_type'    => 'topic',
            ]);
        }
    }

    /**
     * Upsert one row in information_object_physical_location for the IO.
     * Pass null to delete any existing row (the controller passes null when
     * the user leaves every Item Physical Location field blank). The table
     * is UNIQUE on information_object_id so insert-or-update is safe.
     */
    private function upsertItemLocation(int $informationObjectId, ?array $location): void
    {
        if ($location === null) {
            DB::table('information_object_physical_location')
                ->where('information_object_id', $informationObjectId)
                ->delete();
            return;
        }

        $exists = DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->exists();

        if ($exists) {
            DB::table('information_object_physical_location')
                ->where('information_object_id', $informationObjectId)
                ->update($location);
        } else {
            DB::table('information_object_physical_location')->insert(array_merge(
                ['information_object_id' => $informationObjectId],
                $location
            ));
        }
    }

    /**
     * Delete a library item and all related data.
     */
    public function delete(string $slug): void
    {
        $item = $this->getBySlug($slug);
        if (!$item) {
            return;
        }

        $id = $item->id;

        DB::transaction(function () use ($id) {
            // 1. Delete display_object_config
            DB::table('display_object_config')
                ->where('object_id', $id)
                ->where('object_type', 'library')
                ->delete();

            // 2. Delete library_item_creator relationships
            $libraryItemId = DB::table('library_item')
                ->where('information_object_id', $id)
                ->value('id');

            if ($libraryItemId) {
                DB::table('library_item_creator')
                    ->where('library_item_id', $libraryItemId)
                    ->delete();

                DB::table('library_item_subject')
                    ->where('library_item_id', $libraryItemId)
                    ->delete();
            }

            // 3. Delete library_item
            DB::table('library_item')
                ->where('information_object_id', $id)
                ->delete();

            // 4. Delete information_object_i18n
            DB::table('information_object_i18n')->where('id', $id)->delete();

            // 5. Delete information_object
            DB::table('information_object')->where('id', $id)->delete();

            // 6. Delete slug + object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Get form dropdown choices for library item edit/create forms.
     */
    public function getFormChoices(string $culture = null): array
    {
        $culture = $culture ?? $this->culture;

        // Level of description terms (taxonomy_id = 34)
        $levels = DB::table('term')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', 34)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        // Repositories
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        // Material types (static list matching AtoM)
        $materialTypes = collect([
            'monograph' => 'Monograph',
            'ebook' => 'E-book',
            'journal' => 'Journal',
            'periodical' => 'Periodical',
            'manuscript' => 'Manuscript',
            'map' => 'Map',
            'audiovisual' => 'Audiovisual',
            'microform' => 'Microform',
            'electronic' => 'Electronic resource',
            'kit' => 'Kit',
            'other' => 'Other',
        ]);

        // Classification schemes
        $classificationSchemes = collect([
            'LC' => 'Library of Congress (LC)',
            'Dewey' => 'Dewey Decimal Classification',
            'UDC' => 'Universal Decimal Classification (UDC)',
            'Other' => 'Other',
        ]);

        // Creator roles - Issue #59 Tier 3 culture-aware via the COALESCE helper.
        $creatorRoles = \AhgCore\Services\AhgSettingsService::getDropdownChoices('creator_role', false);

        // Languages (ISO 639-1 subset commonly used)
        $languages = [
            'af' => 'Afrikaans',
            'ar' => 'Arabic',
            'de' => 'German',
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'la' => 'Latin',
            'nl' => 'Dutch',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'xh' => 'Xhosa',
            'zh' => 'Chinese',
            'zu' => 'Zulu',
        ];

        // Physical objects for storage container dropdown
        $physicalObjects = DB::table('physical_object as po')
            ->leftJoin('physical_object_i18n as poi', function ($join) use ($culture) {
                $join->on('poi.id', '=', 'po.id')->where('poi.culture', '=', $culture);
            })
            ->select(['po.id', 'poi.name', 'poi.location'])
            ->orderBy('poi.name')
            ->get()
            ->mapWithKeys(function ($po) {
                return [$po->id => $po->name . ($po->location ? ' (' . $po->location . ')' : '')];
            })
            ->toArray();

        return [
            'levels' => $levels,
            'repositories' => $repositories,
            'materialTypes' => $materialTypes,
            'classificationSchemes' => $classificationSchemes,
            'creatorRoles' => $creatorRoles,
            'languages' => $languages,
            'physicalObjects' => $physicalObjects,
        ];
    }

    /**
     * Get the slug for an information_object ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    /**
     * Get creators linked to a library item.
     */
    public function getCreators(int $libraryItemId): \Illuminate\Support\Collection
    {
        return DB::table('library_item_creator')
            ->where('library_item_creator.library_item_id', $libraryItemId)
            ->orderBy('library_item_creator.sort_order')
            ->select([
                'library_item_creator.id',
                'library_item_creator.name',
                'library_item_creator.role',
                'library_item_creator.authority_uri',
            ])
            ->get()
            ->map(function ($creator) {
                // Try to find matching actor for slug link
                $actor = DB::table('actor_i18n')
                    ->join('slug', 'actor_i18n.id', '=', 'slug.object_id')
                    ->where('actor_i18n.authorized_form_of_name', $creator->name)
                    ->where('actor_i18n.culture', $this->culture)
                    ->select('slug.slug')
                    ->first();
                $creator->slug = $actor->slug ?? null;
                return $creator;
            });
    }

    /**
     * Get subjects linked to a library item.
     */
    public function getSubjects(int $libraryItemId): \Illuminate\Support\Collection
    {
        return DB::table('library_item_subject')
            ->where('library_item_subject.library_item_id', $libraryItemId)
            ->select([
                'library_item_subject.id',
                'library_item_subject.heading as name',
                'library_item_subject.subject_type',
                'library_item_subject.source',
                'library_item_subject.uri',
            ])
            ->get();
    }

    /**
     * Resolve a term name by ID.
     */
    public function getTermName(?int $termId): ?string
    {
        if (!$termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $this->culture)
            ->value('name');
    }

    /**
     * Get the parent information_object for a library item (if not root).
     */
    public function getParentItem(int $informationObjectId): ?object
    {
        $parentId = DB::table('information_object')
            ->where('id', $informationObjectId)
            ->value('parent_id');

        // Root ID is 1 in AtoM
        if (!$parentId || $parentId <= 1) {
            return null;
        }

        return DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->where('information_object.id', $parentId)
            ->select([
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Count child information_objects for a library item.
     */
    public function getChildCount(int $informationObjectId): int
    {
        return DB::table('information_object')
            ->where('parent_id', $informationObjectId)
            ->count();
    }

    /**
     * Get item physical location data for an information_object.
     */
    public function getItemLocation(int $informationObjectId): array
    {
        $row = DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->first();

        if (!$row) {
            return [];
        }

        return (array) $row;
    }
}
