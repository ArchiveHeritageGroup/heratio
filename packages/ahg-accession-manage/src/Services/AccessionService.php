<?php

namespace AhgAccessionManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessionService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Get an accession by slug with all related data.
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
     * Get an accession by ID with all fields joined.
     */
    public function getById(int $id): ?object
    {
        return DB::table('accession')
            ->join('object', 'accession.id', '=', 'object.id')
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->where('accession.id', $id)
            ->select([
                'accession.id',
                'accession.identifier',
                'accession.date',
                'accession.acquisition_type_id',
                'accession.processing_priority_id',
                'accession.processing_status_id',
                'accession.resource_type_id',
                'accession.source_culture',
                'accession_i18n.title',
                'accession_i18n.scope_and_content',
                'accession_i18n.appraisal',
                'accession_i18n.archival_history',
                'accession_i18n.location_information',
                'accession_i18n.physical_characteristics',
                'accession_i18n.processing_notes',
                'accession_i18n.received_extent_units',
                'accession_i18n.source_of_acquisition',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Get donors linked to an accession via relation table (type_id = 167).
     */
    public function getDonors(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('actor_i18n', 'relation.subject_id', '=', 'actor_i18n.id')
            ->join('slug', 'relation.subject_id', '=', 'slug.object_id')
            ->where('relation.object_id', $accessionId)
            ->where('relation.type_id', 167)
            ->where('actor_i18n.culture', $this->culture)
            ->select([
                'relation.subject_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get the first (primary) donor for an accession.
     */
    public function getDonor(int $accessionId): ?object
    {
        return $this->getDonors($accessionId)->first();
    }

    /**
     * Get deaccessions for an accession.
     */
    public function getDeaccessions(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('deaccession')
            ->join('deaccession_i18n', 'deaccession.id', '=', 'deaccession_i18n.id')
            ->where('deaccession.accession_id', $accessionId)
            ->where('deaccession_i18n.culture', $this->culture)
            ->select([
                'deaccession.id',
                'deaccession.identifier',
                'deaccession.date',
                'deaccession.scope_id',
                'deaccession_i18n.description',
                'deaccession_i18n.extent',
                'deaccession_i18n.reason',
            ])
            ->get();
    }

    /**
     * Get form dropdown choices for accession edit/create forms.
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
            'acquisitionTypes' => $termLookup(63),
            'processingPriorities' => $termLookup(64),
            'processingStatuses' => $termLookup(65),
            'resourceTypes' => $termLookup(62),
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
     * Batch-resolve term names by IDs.
     */
    public function getTermNames(array $termIds): array
    {
        if (empty($termIds)) {
            return [];
        }

        return DB::table('term_i18n')
            ->whereIn('id', $termIds)
            ->where('culture', $this->culture)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Create a new accession with all related data.
     *
     * @return int The new accession ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitAccession',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // 2. Create accession record
            DB::table('accession')->insert([
                'id' => $id,
                'identifier' => $data['identifier'],
                'date' => $data['date'] ?? null,
                'acquisition_type_id' => $data['acquisition_type_id'] ?? null,
                'processing_priority_id' => $data['processing_priority_id'] ?? null,
                'processing_status_id' => $data['processing_status_id'] ?? null,
                'resource_type_id' => $data['resource_type_id'] ?? null,
                'source_culture' => $this->culture,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Create accession_i18n record
            DB::table('accession_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'title' => $data['title'] ?? null,
                'scope_and_content' => $data['scope_and_content'] ?? null,
                'appraisal' => $data['appraisal'] ?? null,
                'archival_history' => $data['archival_history'] ?? null,
                'location_information' => $data['location_information'] ?? null,
                'physical_characteristics' => $data['physical_characteristics'] ?? null,
                'processing_notes' => $data['processing_notes'] ?? null,
                'received_extent_units' => $data['received_extent_units'] ?? null,
                'source_of_acquisition' => $data['source_of_acquisition'] ?? null,
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

            return $id;
        });
    }

    /**
     * Update an existing accession.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            // 1. Update accession record
            $accessionUpdate = [];
            $accessionFields = [
                'identifier', 'date', 'acquisition_type_id',
                'processing_priority_id', 'processing_status_id', 'resource_type_id',
            ];
            foreach ($accessionFields as $field) {
                if (array_key_exists($field, $data)) {
                    $accessionUpdate[$field] = $data[$field];
                }
            }
            if (!empty($accessionUpdate)) {
                DB::table('accession')->where('id', $id)->update($accessionUpdate);
            }

            // 2. Update accession_i18n (upsert)
            $i18nFields = [
                'title', 'scope_and_content', 'appraisal', 'archival_history',
                'location_information', 'physical_characteristics', 'processing_notes',
                'received_extent_units', 'source_of_acquisition',
            ];
            $i18nData = [];
            foreach ($i18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $i18nData[$field] = $data[$field];
                }
            }
            if (!empty($i18nData)) {
                $exists = DB::table('accession_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('accession_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18nData);
                } else {
                    DB::table('accession_i18n')->insert(array_merge(
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
     * Delete an accession and all related data.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // 1. Delete deaccessions
            $deaccessionIds = DB::table('deaccession')->where('accession_id', $id)->pluck('id')->toArray();
            if (!empty($deaccessionIds)) {
                DB::table('deaccession_i18n')->whereIn('id', $deaccessionIds)->delete();
                DB::table('deaccession')->whereIn('id', $deaccessionIds)->delete();
            }

            // 2. Delete relations (donor link, etc.)
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

            // 3. Delete accession_i18n
            DB::table('accession_i18n')->where('id', $id)->delete();

            // 4. Delete accession record
            DB::table('accession')->where('id', $id)->delete();

            // 5. Delete slug + object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Get the slug for an accession ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }
}
