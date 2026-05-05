<?php

/**
 * AccessionService - Service for Heratio
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

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessionService
{
    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
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
     * Get donors linked to an accession via relation table.
     * AtoM: subject=accession, object=donor, type=RELATION_DONOR.
     */
    public function getDonors(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->join('slug', 'relation.object_id', '=', 'slug.object_id')
            ->where('relation.subject_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_DONOR)
            ->where('actor_i18n.culture', $this->culture)
            ->select([
                'relation.object_id as id',
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

        // Event types for accession events (taxonomy 40)
        $eventTypes = $termLookup(40);

        // Alternative identifier types (taxonomy 66)
        $altIdentifierTypes = $termLookup(66);

        return [
            'acquisitionTypes' => $termLookup(63),
            'processingPriorities' => $termLookup(64),
            'processingStatuses' => $termLookup(65),
            'resourceTypes' => $termLookup(62),
            'eventTypes' => $eventTypes,
            'altIdentifierTypes' => $altIdentifierTypes,
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
    /**
     * Flat snapshot of accession-update fields for the security_audit_log
     * before/after diff.
     */
    private function auditSnapshot(int $id): array
    {
        $acc = (array) (DB::table('accession')->where('id', $id)
            ->select('identifier', 'date', 'acquisition_type_id', 'processing_priority_id',
                'processing_status_id', 'resource_type_id')
            ->first() ?? []);
        $i18n = (array) (DB::table('accession_i18n')->where('id', $id)
            ->where('culture', $this->culture)
            ->select('title', 'scope_and_content', 'appraisal', 'archival_history',
                'location_information', 'physical_characteristics', 'processing_notes',
                'received_extent_units', 'source_of_acquisition')
            ->first() ?? []);
        return array_merge($acc, $i18n);
    }

    public function update(int $id, array $data): void
    {
        $auditBefore = $this->auditSnapshot($id);

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

        $auditAfter = $this->auditSnapshot($id);
        \AhgCore\Support\AuditLog::captureEdit($id, 'accession', $auditBefore, $auditAfter);
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
     * Get accruals for an accession (accessions related to this one via RELATION_ACCRUAL).
     */
    public function getAccruals(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('accession', 'relation.subject_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.object_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_ACCRUAL)
            ->select([
                'accession.id',
                'accession.identifier',
                'accession_i18n.title',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get the accession this one is an accrual to.
     */
    public function getAccrualTo(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('accession', 'relation.object_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_ACCRUAL)
            ->select([
                'accession.id',
                'accession.identifier',
                'accession_i18n.title',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get creators linked to an accession via relation table.
     * AtoM: subject=actor, object=accession, type=RELATION_CREATION (111)
     * — see qtAccessionPlugin/modules/accession/actions/editAction.class.php.
     */
    public function getCreators(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('actor_i18n', 'relation.subject_id', '=', 'actor_i18n.id')
            ->join('slug', 'relation.subject_id', '=', 'slug.object_id')
            ->where('relation.object_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_CREATION)
            ->where('actor_i18n.culture', $this->culture)
            ->select([
                'relation.subject_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get dates for an accession from the event table.
     */
    public function getDates(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n', function ($j) {
                $j->on('event.type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('event.object_id', $accessionId)
            ->select([
                'event.id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'term_i18n.name as type_name',
            ])
            ->get();
    }

    /**
     * Get accession events.
     */
    public function getAccessionEvents(int $accessionId): \Illuminate\Support\Collection
    {
        $events = DB::table('accession_event')
            ->leftJoin('accession_event_i18n', function ($j) {
                $j->on('accession_event.id', '=', 'accession_event_i18n.id')
                    ->where('accession_event_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n', function ($j) {
                $j->on('accession_event.type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('accession_event.accession_id', $accessionId)
            ->select([
                'accession_event.id',
                'accession_event.date',
                'term_i18n.name as type_name',
                'accession_event_i18n.agent',
            ])
            ->get();

        // Get notes from the note table for each event
        foreach ($events as $event) {
            $event->note = DB::table('note')
                ->leftJoin('note_i18n', function ($j) {
                    $j->on('note.id', '=', 'note_i18n.id')
                        ->where('note_i18n.culture', '=', $this->culture);
                })
                ->where('note.object_id', $event->id)
                ->value('note_i18n.content');
        }

        return $events;
    }

    /**
     * Get alternative identifiers for an accession (from other_name table).
     */
    public function getAlternativeIdentifiers(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', $this->culture);
            })
            ->where('other_name.object_id', $accessionId)
            ->select([
                'other_name.id',
                'other_name.type_id',
                'other_name_i18n.name as identifier',
                DB::raw("(SELECT ti.name FROM term_i18n ti WHERE ti.id = other_name.type_id AND ti.culture = '{$this->culture}' LIMIT 1) as label"),
            ])
            ->get();
    }

    /**
     * Get information objects linked to an accession via relation table.
     * AtoM: subject=information_object, object=accession, type=RELATION_ACCESSION (167)
     * — see qtAccessionPlugin/modules/accession/actions/addInformationObjectAction.class.php.
     */
    public function getInformationObjects(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('information_object_i18n', function ($j) {
                $j->on('relation.subject_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'relation.subject_id', '=', 'slug.object_id')
            ->where('relation.object_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_ACCESSION)
            ->select([
                'relation.subject_id as id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get rights records for an accession.
     */
    public function getRights(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('rights', 'relation.object_id', '=', 'rights.id')
            ->leftJoin('rights_i18n', function ($j) {
                $j->on('rights.id', '=', 'rights_i18n.id')
                    ->where('rights_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n', function ($j) {
                $j->on('rights.basis_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('relation.subject_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_RIGHT)
            ->select([
                'rights.id',
                'rights.start_date',
                'rights.end_date',
                'term_i18n.name as basis_name',
                'rights_i18n.rights_note',
            ])
            ->get();
    }

    /**
     * Get physical objects linked to an accession via relation table.
     * AtoM: subject=physical_object, object=accession, type=RELATION_HAS_PHYSICAL_OBJECT.
     */
    public function getPhysicalObjects(int $accessionId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('physical_object', 'relation.subject_id', '=', 'physical_object.id')
            ->leftJoin('physical_object_i18n', function ($j) {
                $j->on('physical_object.id', '=', 'physical_object_i18n.id')
                    ->where('physical_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n', function ($j) {
                $j->on('physical_object.type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'physical_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $accessionId)
            ->where('relation.type_id', TermId::RELATION_HAS_PHYSICAL_OBJECT)
            ->select([
                'physical_object.id',
                'physical_object_i18n.name',
                'physical_object_i18n.location',
                'term_i18n.name as type_name',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Get contact information for a donor.
     */
    public function getDonorContacts(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $actorId)
            ->select([
                'contact_information.id',
                'contact_information_i18n.contact_person',
                'contact_information_i18n.street_address',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
                'contact_information_i18n.postal_code',
                'contact_information_i18n.country_code',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.email',
                'contact_information.website',
            ])
            ->get();
    }

    /**
     * Get the slug for an accession ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    // -------------------------------------------------------------------
    // Settings wiring (admin/ahgSettings/accession). Each method below maps
    // one ahg_settings key into a concrete behaviour for the accession
    // create / save / finalisation flow. Helpers live here so a future
    // finalise endpoint or IO-from-accession flow can reuse them without
    // re-reading settings or re-querying.
    // -------------------------------------------------------------------

    private function settingValue(string $key, $default = null)
    {
        // ahg_settings columns are setting_key / setting_value (not the
        // shorter name/value the helper used in the first draft).
        $row = DB::table('ahg_settings')->where('setting_key', $key)->first();
        return $row ? $row->setting_value : $default;
    }

    private function settingBool(string $key, bool $default = false): bool
    {
        $v = $this->settingValue($key, $default ? 'true' : 'false');
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Render the next accession identifier from the
     * accession_numbering_mask setting. Tokens supported:
     *   {YYYY} → 4-digit year
     *   {####} → zero-padded sequence (length = number of #)
     * Sequence = (max numeric tail seen in accession.identifier with the
     * matching prefix this year) + 1. Defaults to ACC-{YYYY}-{####} so a
     * fresh install with no setting still produces sensible numbers.
     */
    public function nextAccessionNumber(): string
    {
        $mask = (string) $this->settingValue('accession_numbering_mask', 'ACC-{YYYY}-{####}');
        $year = (int) date('Y');

        // Compute the prefix (everything before the {####} token) so we
        // can scan existing identifiers. Substitute {YYYY} first.
        $prefixTemplate = str_replace('{YYYY}', (string) $year, $mask);
        $hashStart = strpos($prefixTemplate, '{');
        $prefix = $hashStart === false ? $prefixTemplate : substr($prefixTemplate, 0, $hashStart);

        $hashLen = 4; // default if mask has no #### token
        if (preg_match('/\{(#+)\}/', $mask, $m)) {
            $hashLen = strlen($m[1]);
        }

        $maxSeq = 0;
        if ($prefix !== '') {
            $rows = DB::table('accession')
                ->where('identifier', 'LIKE', $prefix . '%')
                ->pluck('identifier');
            foreach ($rows as $ident) {
                $tail = substr((string) $ident, strlen($prefix));
                if (preg_match('/^(\d+)/', $tail, $m)) {
                    $n = (int) $m[1];
                    if ($n > $maxSeq) $maxSeq = $n;
                }
            }
        }

        $next = $maxSeq + 1;
        $rendered = str_replace('{YYYY}', (string) $year, $mask);
        $rendered = preg_replace_callback('/\{(#+)\}/', function ($m) use ($next) {
            return str_pad((string) $next, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $rendered);
        return (string) $rendered;
    }

    /**
     * Resolve the configured default priority (low/normal/high/urgent
     * string) to a term_id in taxonomy 64 by case-insensitive name match.
     * Returns null if no setting is configured or no matching term exists.
     */
    public function defaultPriorityTermId(): ?int
    {
        $name = (string) $this->settingValue('accession_default_priority', '');
        if ($name === '') return null;
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 64)
            ->whereRaw('LOWER(term_i18n.name) = ?', [strtolower($name)])
            ->where('term_i18n.culture', $this->culture)
            ->value('term.id') ?: null;
    }

    public function autoAssignEnabled(): bool
    {
        return $this->settingBool('accession_auto_assign_enabled', false);
    }

    public function containerBarcodesEnabled(): bool
    {
        return $this->settingBool('accession_allow_container_barcodes', false);
    }

    public function rightsInheritanceEnabled(): bool
    {
        return $this->settingBool('accession_rights_inheritance_enabled', false);
    }

    /**
     * Upsert accession_v2 workflow row. Used by the auto-assign hook on
     * accession create and any future finalise transition.
     */
    public function upsertWorkflow(int $accessionId, array $fields): void
    {
        $exists = DB::table('accession_v2')->where('accession_id', $accessionId)->exists();
        if ($exists) {
            DB::table('accession_v2')->where('accession_id', $accessionId)->update($fields);
        } else {
            DB::table('accession_v2')->insert(array_merge(
                ['accession_id' => $accessionId],
                $fields
            ));
        }
    }

    /**
     * Finalisation prerequisites. Returns a list of human-readable strings
     * naming each requirement that's still missing for this accession.
     * Empty array means the accession can be finalised. Honours the
     * accession_require_donor_agreement and accession_require_appraisal
     * settings — a setting that's false skips its check entirely. Callable
     * from a future finalise endpoint or surfaced as a banner on show.
     */
    public function finalisationBlockers(int $accessionId): array
    {
        $blockers = [];

        if ($this->settingBool('accession_require_donor_agreement')) {
            $hasAgreement = DB::table('accession_attachment')
                ->where('accession_id', $accessionId)
                ->where('category', 'donor_agreement')
                ->exists();
            if (!$hasAgreement) $blockers[] = 'Donor agreement attachment missing';
        }

        if ($this->settingBool('accession_require_appraisal')) {
            $hasAppraisal = DB::table('accession_appraisal')
                ->where('accession_id', $accessionId)
                ->where('recommendation', '!=', 'pending')
                ->exists();
            if (!$hasAppraisal) $blockers[] = 'Appraisal not completed';
        }

        return $blockers;
    }

    /**
     * Copy donor's PREMIS rights from the accession to a newly-created IO.
     * Honours accession_rights_inheritance_enabled — a no-op when the
     * setting is false. Idempotent: existing rows for the same IO are
     * left alone. Called from any flow that materialises an IO out of an
     * accession (the create-IO-from-accession flow doesn't exist yet, but
     * the helper is ready when it lands).
     */
    public function inheritRightsToIo(int $accessionId, int $ioId): int
    {
        if (!$this->rightsInheritanceEnabled()) return 0;

        // accession_rights_inherited is a join table (rights_id +
        // information_object_id), not a copy. Idempotent: skip rows that
        // are already linked. Honours each rights row's inherit_to_children
        // flag — rows with that flag set to 0 stay attached to the
        // accession only and don't propagate.
        $rows = DB::table('accession_rights')
            ->where('accession_id', $accessionId)
            ->where('inherit_to_children', 1)
            ->select('id')
            ->get();
        $applied = 0;
        foreach ($rows as $r) {
            $exists = DB::table('accession_rights_inherited')
                ->where('rights_id', $r->id)
                ->where('information_object_id', $ioId)
                ->exists();
            if (!$exists) {
                DB::table('accession_rights_inherited')->insert([
                    'rights_id'             => $r->id,
                    'information_object_id' => $ioId,
                    'applied_by'            => auth()->id(),
                ]);
                $applied++;
            }
        }
        return $applied;
    }
}
