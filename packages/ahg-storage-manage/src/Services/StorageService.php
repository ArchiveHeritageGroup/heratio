<?php

/**
 * StorageService - Service for Heratio
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



namespace AhgStorageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorageService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return null;
        }

        return $this->getById($objectId);
    }

    public function getById(int $id): ?object
    {
        return DB::table('physical_object')
            ->join('object', 'physical_object.id', '=', 'object.id')
            ->join('slug', 'physical_object.id', '=', 'slug.object_id')
            ->leftJoin('physical_object_i18n', function ($j) {
                $j->on('physical_object.id', '=', 'physical_object_i18n.id')
                    ->where('physical_object_i18n.culture', '=', $this->culture);
            })
            ->where('physical_object.id', $id)
            ->select([
                'physical_object.id',
                'physical_object.type_id',
                'physical_object.source_culture',
                'physical_object_i18n.name',
                'physical_object_i18n.description',
                'physical_object_i18n.location',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Linked archival descriptions.
     * AtoM relation: subject_id = physical_object.id, object_id = information_object.id, type_id = 147 (HAS_PHYSICAL_OBJECT_ID).
     */
    public function getLinkedDescriptions(int $physicalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('information_object', 'relation.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $physicalObjectId)
            ->where('relation.type_id', 147)
            ->select([
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Linked accessions.
     * AtoM relation: subject_id = physical_object.id, object_id = accession.id, type_id = 147 (HAS_PHYSICAL_OBJECT_ID).
     */
    public function getLinkedAccessions(int $physicalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('accession', 'relation.object_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $physicalObjectId)
            ->where('relation.type_id', 147)
            ->select([
                'accession.id',
                'accession_i18n.title',
                'accession.identifier',
                'slug.slug',
            ])
            ->get();
    }

    public function getFormChoices(): array
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 48)
            ->where('term_i18n.culture', $this->culture)
            ->orderBy('term_i18n.name')
            ->pluck('term_i18n.name', 'term.id')
            ->all();
    }

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

    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitPhysicalObject',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            $baseSlug = Str::slug($data['name'] ?? 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

            DB::table('physical_object')->insert([
                'id' => $id,
                'type_id' => $data['type_id'] ?? null,
                'source_culture' => $this->culture,
            ]);

            DB::table('physical_object_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
            ]);

            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            // Update physical_object base table
            $baseUpdate = [];
            if (array_key_exists('type_id', $data)) {
                $baseUpdate['type_id'] = $data['type_id'];
            }
            if (!empty($baseUpdate)) {
                DB::table('physical_object')->where('id', $id)->update($baseUpdate);
            }

            // Update i18n fields
            $i18nFields = ['name', 'description', 'location'];
            $i18n = [];
            foreach ($i18nFields as $f) {
                if (array_key_exists($f, $data)) {
                    $i18n[$f] = $data[$f];
                }
            }
            if (!empty($i18n)) {
                $exists = DB::table('physical_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();
                if ($exists) {
                    DB::table('physical_object_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18n);
                } else {
                    DB::table('physical_object_i18n')->insert(
                        array_merge(['id' => $id, 'culture' => $this->culture], $i18n)
                    );
                }
            }

            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // Delete linked relations
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

            // Delete notes
            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            DB::table('object_term_relation')->where('object_id', $id)->delete();
            DB::table('physical_object_i18n')->where('id', $id)->delete();
            DB::table('physical_object')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    // ── Extended data (physical_object_extended table) ──────────────

    private const EXTENDED_FIELDS = [
        'building', 'floor', 'room', 'aisle', 'bay', 'rack', 'shelf', 'position',
        'barcode', 'reference_code',
        'width', 'height', 'depth',
        'total_capacity', 'used_capacity', 'capacity_unit',
        'total_linear_metres', 'used_linear_metres',
        'climate_controlled', 'temperature_min', 'temperature_max',
        'humidity_min', 'humidity_max',
        'security_level', 'access_restrictions', 'status', 'notes',
    ];

    public function getExtendedData(int $physicalObjectId): array
    {
        $row = DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->first();

        if (!$row) {
            return [];
        }

        return (array) $row;
    }

    public function saveExtendedData(int $physicalObjectId, array $data): void
    {
        $values = [];
        foreach (self::EXTENDED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                // Convert empty strings to null for numeric/text fields
                if ($value === '' || $value === null) {
                    $value = null;
                }
                // climate_controlled is boolean
                if ($field === 'climate_controlled') {
                    $value = !empty($data[$field]) ? 1 : 0;
                }
                $values[$field] = $value;
            }
        }

        if (empty($values)) {
            return;
        }

        $exists = DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->exists();

        if ($exists) {
            $values['updated_at'] = now();
            DB::table('physical_object_extended')
                ->where('physical_object_id', $physicalObjectId)
                ->update($values);
        } else {
            $values['physical_object_id'] = $physicalObjectId;
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('physical_object_extended')->insert($values);
        }
    }

    public function deleteExtendedData(int $physicalObjectId): void
    {
        DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->delete();
    }
}
