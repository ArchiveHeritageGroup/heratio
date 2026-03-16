<?php

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

    /**
     * Get a function by ID with all ISDF fields.
     */
    public function getById(int $id): ?object
    {
        return DB::table('function_object')
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

            // 4. Generate slug
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

            // 3. Touch the object record
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
}
