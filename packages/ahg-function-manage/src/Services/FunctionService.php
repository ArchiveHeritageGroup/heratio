<?php

/**
 * FunctionService - Service for Heratio
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



namespace AhgFunctionManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FunctionService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Get a function by slug with all ISDF fields.
     */
    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return null;
        }

        return $this->getById($objectId);
    }

    // AtoM term IDs for other_name types
    protected const PARALLEL_FORM_OF_NAME_ID = 148;
    protected const OTHER_FORM_OF_NAME_ID = 149;
    // AtoM term ID for maintenance note
    protected const MAINTENANCE_NOTE_ID = 127;

    /**
     * Get a function by ID with all ISDF fields.
     */
    public function getById(int $id): ?object
    {
        $row = DB::table('function_object')
            ->join('object', 'function_object.id', '=', 'object.id')
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->leftJoin('function_object_i18n', function ($j) {
                $j->on('function_object.id', '=', 'function_object_i18n.id')
                    ->where('function_object_i18n.culture', '=', $this->culture);
            })
            ->where('function_object.id', $id)
            ->select([
                'function_object.id',
                'function_object.type_id',
                'function_object.description_status_id',
                'function_object.description_detail_id',
                'function_object.description_identifier',
                'function_object.source_standard',
                'function_object.source_culture',
                'function_object_i18n.authorized_form_of_name',
                'function_object_i18n.classification',
                'function_object_i18n.dates',
                'function_object_i18n.description',
                'function_object_i18n.history',
                'function_object_i18n.legislation',
                'function_object_i18n.institution_identifier',
                'function_object_i18n.revision_history',
                'function_object_i18n.rules',
                'function_object_i18n.sources',
                'object.created_at',
                'object.updated_at',
                'object.serial_number',
                'slug.slug',
            ])
            ->first();

        if (!$row) {
            return null;
        }

        // Fetch parallel_name from other_name table (type_id = 148)
        $row->parallel_name = $this->getOtherNameValue($id, self::PARALLEL_FORM_OF_NAME_ID);

        // Fetch other_name from other_name table (type_id = 149)
        $row->other_name = $this->getOtherNameValue($id, self::OTHER_FORM_OF_NAME_ID);

        // Fetch maintenance_notes from note table (type_id = 127)
        $row->maintenance_notes = $this->getMaintenanceNote($id);

        return $row;
    }

    /**
     * Get a single other_name value for a function by type.
     */
    protected function getOtherNameValue(int $objectId, int $typeId): ?string
    {
        return DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', $this->culture);
            })
            ->where('other_name.object_id', $objectId)
            ->where('other_name.type_id', $typeId)
            ->value('other_name_i18n.name');
    }

    /**
     * Get the maintenance note content for a function.
     */
    protected function getMaintenanceNote(int $objectId): ?string
    {
        return DB::table('note')
            ->leftJoin('note_i18n', function ($j) {
                $j->on('note.id', '=', 'note_i18n.id')
                    ->where('note_i18n.culture', '=', $this->culture);
            })
            ->where('note.object_id', $objectId)
            ->where('note.type_id', self::MAINTENANCE_NOTE_ID)
            ->value('note_i18n.content');
    }

    /**
     * Get related functions (bidirectional via relation table).
     */
    public function getRelatedFunctions(int $functionId): \Illuminate\Support\Collection
    {
        $asSubject = DB::table('relation')
            ->join('function_object', 'relation.object_id', '=', 'function_object.id')
            ->leftJoin('function_object_i18n', function ($j) {
                $j->on('function_object.id', '=', 'function_object_i18n.id')
                    ->where('function_object_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $functionId)
            ->select([
                'function_object.id',
                'function_object_i18n.authorized_form_of_name',
                'slug.slug',
            ])
            ->get();

        $asObject = DB::table('relation')
            ->join('function_object', 'relation.subject_id', '=', 'function_object.id')
            ->leftJoin('function_object_i18n', function ($j) {
                $j->on('function_object.id', '=', 'function_object_i18n.id')
                    ->where('function_object_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $functionId)
            ->select([
                'function_object.id',
                'function_object_i18n.authorized_form_of_name',
                'slug.slug',
            ])
            ->get();

        return $asSubject->merge($asObject)->unique('id');
    }

    /**
     * Get related resources (information objects linked via relation table).
     */
    public function getRelatedResources(int $functionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('information_object', 'relation.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $functionId)
            ->where('information_object.id', '!=', 1)
            ->select([
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->distinct()
            ->limit(50)
            ->get();
    }

    /**
     * Get form dropdown choices for function edit/create forms.
     */
    public function getFormChoices(): array
    {
        $termLookup = function (int $taxonomyId) {
            return DB::table('term')
                ->leftJoin('term_i18n', function ($j) {
                    $j->on('term.id', '=', 'term_i18n.id')
                        ->where('term_i18n.culture', '=', $this->culture);
                })
                ->where('term.taxonomy_id', $taxonomyId)
                ->select('term.id', 'term_i18n.name')
                ->orderBy('term_i18n.name')
                ->get();
        };

        return [
            'functionTypes' => $termLookup(43),           // ISDF Function Types
            'descriptionStatuses' => $termLookup(33),     // Description Statuses
            'descriptionDetails' => $termLookup(31),      // Description Detail Levels
        ];
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
     * Create a new function with all related data.
     *
     * @return int The new function ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitFunctionObject',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // 2. Create function_object record
            DB::table('function_object')->insert([
                'id' => $id,
                'type_id' => $data['type_id'] ?? null,
                'description_status_id' => $data['description_status_id'] ?? null,
                'description_detail_id' => $data['description_detail_id'] ?? null,
                'description_identifier' => $data['description_identifier'] ?? null,
                'source_standard' => $data['source_standard'] ?? null,
                'source_culture' => $this->culture,
            ]);

            // 3. Create function_object_i18n record
            DB::table('function_object_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'authorized_form_of_name' => $data['authorized_form_of_name'] ?? null,
                'classification' => $data['classification'] ?? null,
                'dates' => $data['dates'] ?? null,
                'description' => $data['description'] ?? null,
                'history' => $data['history'] ?? null,
                'legislation' => $data['legislation'] ?? null,
                'institution_identifier' => $data['institution_identifier'] ?? null,
                'revision_history' => $data['revision_history'] ?? null,
                'rules' => $data['rules'] ?? null,
                'sources' => $data['sources'] ?? null,
            ]);

            // 4. Save parallel_name to other_name table
            if (!empty($data['parallel_name'])) {
                $this->saveOtherName($id, self::PARALLEL_FORM_OF_NAME_ID, $data['parallel_name']);
            }

            // 5. Save other_name to other_name table
            if (!empty($data['other_name'])) {
                $this->saveOtherName($id, self::OTHER_FORM_OF_NAME_ID, $data['other_name']);
            }

            // 6. Save maintenance_notes to note table
            if (!empty($data['maintenance_notes'])) {
                $this->saveMaintenanceNote($id, $data['maintenance_notes']);
            }

            // 7. Generate slug
            $baseSlug = Str::slug($data['authorized_form_of_name'] ?? 'untitled');
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

            return $id;
        });
    }

    /**
     * Update an existing function.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            // 1. Update function_object record
            $functionUpdate = [];
            $functionFields = [
                'type_id', 'description_status_id', 'description_detail_id',
                'description_identifier', 'source_standard',
            ];
            foreach ($functionFields as $field) {
                if (array_key_exists($field, $data)) {
                    $functionUpdate[$field] = $data[$field];
                }
            }
            if (!empty($functionUpdate)) {
                DB::table('function_object')->where('id', $id)->update($functionUpdate);
            }

            // 2. Update function_object_i18n (ISDF fields) - upsert
            $i18nFields = [
                'authorized_form_of_name', 'classification', 'dates', 'description',
                'history', 'legislation', 'institution_identifier', 'revision_history',
                'rules', 'sources',
            ];
            $i18nData = [];
            foreach ($i18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $i18nData[$field] = $data[$field];
                }
            }
            if (!empty($i18nData)) {
                $exists = DB::table('function_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('function_object_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18nData);
                } else {
                    DB::table('function_object_i18n')->insert(array_merge(
                        ['id' => $id, 'culture' => $this->culture],
                        $i18nData
                    ));
                }
            }

            // 3. Save parallel_name to other_name table
            if (array_key_exists('parallel_name', $data)) {
                $this->upsertOtherName($id, self::PARALLEL_FORM_OF_NAME_ID, $data['parallel_name']);
            }

            // 4. Save other_name to other_name table
            if (array_key_exists('other_name', $data)) {
                $this->upsertOtherName($id, self::OTHER_FORM_OF_NAME_ID, $data['other_name']);
            }

            // 5. Save maintenance_notes to note table
            if (array_key_exists('maintenance_notes', $data)) {
                $this->upsertMaintenanceNote($id, $data['maintenance_notes']);
            }

            // 6. Touch the object record
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Delete a function and all related data.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // 1. Delete relations (function-to-function, function-to-resource)
            $relationIds = DB::table('relation')
                ->where('subject_id', $id)
                ->orWhere('object_id', $id)
                ->pluck('id')
                ->toArray();
            if (!empty($relationIds)) {
                DB::table('relation_i18n')->whereIn('id', $relationIds)->delete();
                DB::table('relation')->whereIn('id', $relationIds)->delete();
                DB::table('slug')->whereIn('object_id', $relationIds)->delete();
                DB::table('object')->whereIn('id', $relationIds)->delete();
            }

            // 2. Delete notes
            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            // 3. Delete term relations (access points)
            DB::table('object_term_relation')->where('object_id', $id)->delete();

            // 4. Delete function_object_i18n
            DB::table('function_object_i18n')->where('id', $id)->delete();

            // 5. Delete function_object record
            DB::table('function_object')->where('id', $id)->delete();

            // 6. Delete slug + object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Get the slug for a function ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    /**
     * Save a new other_name record (for create).
     */
    protected function saveOtherName(int $objectId, int $typeId, string $name): void
    {
        $onId = DB::table('other_name')->insertGetId([
            'object_id' => $objectId,
            'type_id' => $typeId,
            'source_culture' => $this->culture,
            'serial_number' => 0,
        ]);

        DB::table('other_name_i18n')->insert([
            'id' => $onId,
            'culture' => $this->culture,
            'name' => $name,
        ]);
    }

    /**
     * Upsert an other_name record (for update).
     */
    protected function upsertOtherName(int $objectId, int $typeId, ?string $name): void
    {
        $existing = DB::table('other_name')
            ->where('object_id', $objectId)
            ->where('type_id', $typeId)
            ->first();

        if ($existing) {
            if (!empty($name)) {
                // Update existing
                $i18nExists = DB::table('other_name_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($i18nExists) {
                    DB::table('other_name_i18n')
                        ->where('id', $existing->id)
                        ->where('culture', $this->culture)
                        ->update(['name' => $name]);
                } else {
                    DB::table('other_name_i18n')->insert([
                        'id' => $existing->id,
                        'culture' => $this->culture,
                        'name' => $name,
                    ]);
                }
            } else {
                // Delete if cleared
                DB::table('other_name_i18n')->where('id', $existing->id)->delete();
                DB::table('other_name')->where('id', $existing->id)->delete();
            }
        } elseif (!empty($name)) {
            $this->saveOtherName($objectId, $typeId, $name);
        }
    }

    /**
     * Save a new maintenance note (for create).
     */
    protected function saveMaintenanceNote(int $objectId, string $content): void
    {
        $noteId = DB::table('note')->insertGetId([
            'object_id' => $objectId,
            'type_id' => self::MAINTENANCE_NOTE_ID,
            'source_culture' => $this->culture,
            'serial_number' => 0,
        ]);

        DB::table('note_i18n')->insert([
            'id' => $noteId,
            'culture' => $this->culture,
            'content' => $content,
        ]);
    }

    /**
     * Upsert a maintenance note (for update).
     */
    protected function upsertMaintenanceNote(int $objectId, ?string $content): void
    {
        $existing = DB::table('note')
            ->where('object_id', $objectId)
            ->where('type_id', self::MAINTENANCE_NOTE_ID)
            ->first();

        if ($existing) {
            if (!empty($content)) {
                $i18nExists = DB::table('note_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($i18nExists) {
                    DB::table('note_i18n')
                        ->where('id', $existing->id)
                        ->where('culture', $this->culture)
                        ->update(['content' => $content]);
                } else {
                    DB::table('note_i18n')->insert([
                        'id' => $existing->id,
                        'culture' => $this->culture,
                        'content' => $content,
                    ]);
                }
            } else {
                // Delete if cleared
                DB::table('note_i18n')->where('id', $existing->id)->delete();
                DB::table('note')->where('id', $existing->id)->delete();
            }
        } elseif (!empty($content)) {
            $this->saveMaintenanceNote($objectId, $content);
        }
    }
}
