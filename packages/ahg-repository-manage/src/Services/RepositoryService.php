<?php

/**
 * RepositoryService - Service for Heratio
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



namespace AhgRepositoryManage\Services;

use AhgCore\Constants\TermId;
use AhgCore\Traits\WithCultureFallback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepositoryService
{
    use WithCultureFallback;

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Get a repository by slug with all ISDIAH + ISAAR fields.
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
     * Get a repository by ID with full class table inheritance join.
     */
    public function getById(int $id): ?object
    {
        // Two i18n tables — actor_i18n (ISAAR) + repository_i18n (ISDIAH).
        // Each gets a current+fallback pair via WithCultureFallback trait.
        return DB::table('repository')
            ->join('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->tap(fn ($q) => $this->joinI18nWithFallback($q, 'actor_i18n', 'repository', aliasPrefix: 'ai'))
            ->tap(fn ($q) => $this->joinI18nWithFallback($q, 'repository_i18n', 'repository', aliasPrefix: 'ri'))
            ->where('repository.id', $id)
            ->where('object.class_name', 'QubitRepository')
            ->select([
                'repository.id',
                'repository.identifier',
                'repository.desc_status_id',
                'repository.desc_detail_id',
                'repository.desc_identifier',
                'repository.upload_limit',
                'repository.source_culture',
                'actor.entity_type_id',
                'actor.description_status_id',
                'actor.description_detail_id',
                'actor.description_identifier',
                'actor.source_standard',
                'actor.corporate_body_identifiers',
                'actor.parent_id',
                'actor.icip_sensitivity',
                // Actor i18n (ISAAR) — culture fallback
                DB::raw('COALESCE(ai_cur.authorized_form_of_name, ai_fb.authorized_form_of_name) AS authorized_form_of_name'),
                DB::raw('COALESCE(ai_cur.dates_of_existence, ai_fb.dates_of_existence) AS dates_of_existence'),
                DB::raw('COALESCE(ai_cur.history, ai_fb.history) AS history'),
                DB::raw('COALESCE(ai_cur.places, ai_fb.places) AS places'),
                DB::raw('COALESCE(ai_cur.legal_status, ai_fb.legal_status) AS legal_status'),
                DB::raw('COALESCE(ai_cur.functions, ai_fb.functions) AS functions'),
                DB::raw('COALESCE(ai_cur.mandates, ai_fb.mandates) AS mandates'),
                DB::raw('COALESCE(ai_cur.internal_structures, ai_fb.internal_structures) AS internal_structures'),
                DB::raw('COALESCE(ai_cur.general_context, ai_fb.general_context) AS general_context'),
                DB::raw('COALESCE(ai_cur.institution_responsible_identifier, ai_fb.institution_responsible_identifier) AS institution_responsible_identifier'),
                DB::raw('COALESCE(ai_cur.rules, ai_fb.rules) AS rules'),
                DB::raw('COALESCE(ai_cur.sources, ai_fb.sources) AS sources'),
                DB::raw('COALESCE(ai_cur.revision_history, ai_fb.revision_history) AS revision_history'),
                // Repository i18n (ISDIAH) — culture fallback
                DB::raw('COALESCE(ri_cur.geocultural_context, ri_fb.geocultural_context) AS geocultural_context'),
                DB::raw('COALESCE(ri_cur.collecting_policies, ri_fb.collecting_policies) AS collecting_policies'),
                DB::raw('COALESCE(ri_cur.buildings, ri_fb.buildings) AS buildings'),
                DB::raw('COALESCE(ri_cur.holdings, ri_fb.holdings) AS holdings'),
                DB::raw('COALESCE(ri_cur.finding_aids, ri_fb.finding_aids) AS finding_aids'),
                DB::raw('COALESCE(ri_cur.opening_times, ri_fb.opening_times) AS opening_times'),
                DB::raw('COALESCE(ri_cur.access_conditions, ri_fb.access_conditions) AS access_conditions'),
                DB::raw('COALESCE(ri_cur.disabled_access, ri_fb.disabled_access) AS disabled_access'),
                DB::raw('COALESCE(ri_cur.research_services, ri_fb.research_services) AS research_services'),
                DB::raw('COALESCE(ri_cur.reproduction_services, ri_fb.reproduction_services) AS reproduction_services'),
                DB::raw('COALESCE(ri_cur.public_facilities, ri_fb.public_facilities) AS public_facilities'),
                DB::raw('COALESCE(ri_cur.desc_institution_identifier, ri_fb.desc_institution_identifier) AS desc_institution_identifier'),
                DB::raw('COALESCE(ri_cur.desc_rules, ri_fb.desc_rules) AS desc_rules'),
                DB::raw('COALESCE(ri_cur.desc_sources, ri_fb.desc_sources) AS desc_sources'),
                DB::raw('COALESCE(ri_cur.desc_revision_history, ri_fb.desc_revision_history) AS desc_revision_history'),
                // Object
                'object.created_at',
                'object.updated_at',
                'object.serial_number',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Get contact information for a repository.
     */
    public function getContacts(int $repoId): \Illuminate\Support\Collection
    {
        return DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $repoId)
            ->select([
                'contact_information.id',
                'contact_information.primary_contact',
                'contact_information.contact_person',
                'contact_information.street_address',
                'contact_information.website',
                'contact_information.email',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.postal_code',
                'contact_information.country_code',
                'contact_information.longitude',
                'contact_information.latitude',
                'contact_information.contact_note',
                'contact_information_i18n.contact_type',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
                'contact_information_i18n.note',
            ])
            ->get();
    }

    /**
     * Get digital objects for this repository.
     */
    public function getDigitalObjects(int $repoId): array
    {
        return \AhgCore\Services\DigitalObjectService::getForObject($repoId);
    }

    /**
     * Get holdings count (information objects in this repository).
     */
    public function getHoldingsCount(int $repoId): int
    {
        return DB::table('information_object')
            ->where('repository_id', $repoId)
            ->where('id', '!=', 1)
            ->count();
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
     * Get other names (parallel, other forms) for a repository.
     */
    public function getOtherNames(int $repoId): \Illuminate\Support\Collection
    {
        return DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', $this->culture);
            })
            ->where('other_name.object_id', $repoId)
            ->select(
                'other_name.id',
                'other_name.type_id',
                'other_name_i18n.name'
            )
            ->get();
    }

    /**
     * Get repository type(s) from object_term_relation (taxonomy 38).
     */
    public function getRepositoryTypes(int $repoId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('object_term_relation.object_id', $repoId)
            ->where('term.taxonomy_id', 38) // Repository Types
            ->select('term.id', 'term_i18n.name')
            ->get();
    }

    /**
     * Get language(s) from property table.
     */
    public function getLanguages(int $repoId): array
    {
        $row = DB::table('property')
            ->leftJoin('property_i18n', function ($j) {
                $j->on('property.id', '=', 'property_i18n.id')
                    ->where('property_i18n.culture', '=', $this->culture);
            })
            ->where('property.object_id', $repoId)
            ->where('property.name', 'language')
            ->value('property_i18n.value');

        if (!$row) {
            return [];
        }

        $decoded = @unserialize($row);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [$row];
    }

    /**
     * Get script(s) from property table.
     */
    public function getScripts(int $repoId): array
    {
        $row = DB::table('property')
            ->leftJoin('property_i18n', function ($j) {
                $j->on('property.id', '=', 'property_i18n.id')
                    ->where('property_i18n.culture', '=', $this->culture);
            })
            ->where('property.object_id', $repoId)
            ->where('property.name', 'script')
            ->value('property_i18n.value');

        if (!$row) {
            return [];
        }

        $decoded = @unserialize($row);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [$row];
    }

    /**
     * Get maintenance notes (stored in note table, type_id=174).
     */
    public function getMaintenanceNotes(int $repoId): ?string
    {
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $repoId)
            ->where('note.type_id', 174)
            ->where('note_i18n.culture', $this->culture)
            ->value('note_i18n.content');
    }

    /**
     * Get thematic area access points (taxonomy 72).
     */
    public function getThematicAreas(int $repoId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('object_term_relation.object_id', $repoId)
            ->where('term.taxonomy_id', 72) // Thematic Area
            ->select('term.id', 'term_i18n.name')
            ->get();
    }

    /**
     * Get geographic subregion access points (taxonomy 73).
     */
    public function getGeographicSubregions(int $repoId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('object_term_relation.object_id', $repoId)
            ->where('term.taxonomy_id', 73) // Geographic Subregion
            ->select('term.id', 'term_i18n.name')
            ->get();
    }

    /**
     * Get paginated top-level holdings (information objects) for a repository.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getHoldingsPaginated(int $repoId, int $perPage = 10, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.repository_id', $repoId)
            ->where('information_object.parent_id', 1) // top-level only
            ->where('information_object.id', '!=', 1)
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->orderBy('information_object_i18n.title')
            ->paginate($perPage, ['*'], 'holdings_page', $page);
    }

    /**
     * Get maintained actors (authority records) for a repository,
     * i.e. actors whose maintaining_repository_id = this repo.
     *
     * @return array  ['label' => string, 'moreUrl' => string, 'dataUrl' => string, 'pager' => LengthAwarePaginator, 'items' => Collection]
     */
    public function getMaintainedActors(int $repoId, int $perPage = 10, int $page = 1): ?array
    {
        $pager = DB::table('actor')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->where('object.class_name', 'QubitActor')
            ->where(function ($q) use ($repoId) {
                // AtoM links maintained actors via a relation or via maintaining_repository_id property
                // The property table stores 'maintainingRepositoryId' for actors
                $q->whereIn('actor.id', function ($sub) use ($repoId) {
                    $sub->select('id')
                        ->from('actor')
                        ->where('parent_id', $repoId);
                })
                ->orWhereIn('actor.id', function ($sub) use ($repoId) {
                    $sub->select('object_id')
                        ->from('property')
                        ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
                        ->where('property.name', 'maintainingRepositoryId')
                        ->where('property_i18n.value', (string) $repoId);
                });
            })
            ->where('actor.id', '!=', 3) // Exclude ROOT actor
            ->select(
                'actor.id',
                'actor_i18n.authorized_form_of_name',
                'slug.slug'
            )
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->paginate($perPage, ['*'], 'actors_page', $page);

        if ($pager->total() === 0) {
            return null;
        }

        $slug = $this->getSlug($repoId);

        return [
            'label' => __('Maintained actors'),
            'moreUrl' => route('actor.browse', ['repository' => $repoId]),
            'dataUrl' => route('repository.show', ['slug' => $slug]),
            'pager' => $pager,
            'items' => $pager->getCollection(),
        ];
    }

    /**
     * Get form dropdown choices for repository edit/create forms.
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
            'descriptionStatuses' => $termLookup(33),  // Description Statuses
            'descriptionDetails' => $termLookup(31),   // Description Detail Levels
        ];
    }

    /**
     * Create a new repository with all related data.
     *
     * @return int The new repository ID
     */
    public function create(array $data): int
    {
        $newId = DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitRepository',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // 2. Generate slug
            $baseSlug = Str::slug($data['authorized_form_of_name'] ?? 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

            // 3. Create actor record (class table inheritance)
            $actorInsert = [
                'id' => $id,
                'entity_type_id' => TermId::ACTOR_ENTITY_CORPORATE_BODY, // repositories are always corporate bodies
                'parent_id' => 3, // Actor::ROOT_ID
                'source_culture' => $this->culture,
            ];
            if (array_key_exists('icip_sensitivity', $data) && $data['icip_sensitivity'] !== '') {
                $actorInsert['icip_sensitivity'] = $data['icip_sensitivity'];
            }
            DB::table('actor')->insert($actorInsert);

            // 4. Create repository record
            DB::table('repository')->insert([
                'id' => $id,
                'identifier' => $data['identifier'] ?? null,
                'desc_status_id' => $data['desc_status_id'] ?? null,
                'desc_detail_id' => $data['desc_detail_id'] ?? null,
                'desc_identifier' => $data['desc_identifier'] ?? null,
                'upload_limit' => $data['upload_limit'] ?? null,
                'source_culture' => $this->culture,
            ]);

            // 5. Save actor_i18n (ISAAR fields)
            DB::table('actor_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'authorized_form_of_name' => $data['authorized_form_of_name'] ?? null,
                'dates_of_existence' => $data['dates_of_existence'] ?? null,
                'history' => $data['history'] ?? null,
                'places' => $data['places'] ?? null,
                'legal_status' => $data['legal_status'] ?? null,
                'functions' => $data['functions'] ?? null,
                'mandates' => $data['mandates'] ?? null,
                'internal_structures' => $data['internal_structures'] ?? null,
                'general_context' => $data['general_context'] ?? null,
                'institution_responsible_identifier' => $data['institution_responsible_identifier'] ?? null,
                'rules' => $data['rules'] ?? null,
                'sources' => $data['sources'] ?? null,
                'revision_history' => $data['revision_history'] ?? null,
            ]);

            // 6. Save repository_i18n (ISDIAH fields)
            DB::table('repository_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'geocultural_context' => $data['geocultural_context'] ?? null,
                'collecting_policies' => $data['collecting_policies'] ?? null,
                'buildings' => $data['buildings'] ?? null,
                'holdings' => $data['holdings'] ?? null,
                'finding_aids' => $data['finding_aids'] ?? null,
                'opening_times' => $data['opening_times'] ?? null,
                'access_conditions' => $data['access_conditions'] ?? null,
                'disabled_access' => $data['disabled_access'] ?? null,
                'research_services' => $data['research_services'] ?? null,
                'reproduction_services' => $data['reproduction_services'] ?? null,
                'public_facilities' => $data['public_facilities'] ?? null,
                'desc_institution_identifier' => $data['desc_institution_identifier'] ?? null,
                'desc_rules' => $data['desc_rules'] ?? null,
                'desc_sources' => $data['desc_sources'] ?? null,
                'desc_revision_history' => $data['desc_revision_history'] ?? null,
            ]);

            // 7. Save parallel name(s) (other_name table, type_id 148)
            if (!empty($data['parallel_name'])) {
                $this->saveOtherName($id, $data['parallel_name'], 148);
            }

            // 8. Save other name(s) (other_name table, type_id 149)
            if (!empty($data['other_name'])) {
                $this->saveOtherName($id, $data['other_name'], 149);
            }

            // 9. Save maintenance notes (note table, type_id 174)
            if (!empty($data['maintenance_notes'])) {
                $this->saveMaintenanceNotes($id, $data['maintenance_notes']);
            }

            // 10. Save contacts if provided
            if (!empty($data['contacts'])) {
                $this->saveContacts($id, $data['contacts']);
            }

            return $id;
        });

        \AhgCore\Support\AuditLog::captureCreate((int) $newId, 'repository', $this->auditSnapshot((int) $newId));
        return (int) $newId;
    }

    /**
     * Update an existing repository.
     */
    /**
     * Flat snapshot of repository-update fields for the security_audit_log
     * before/after diff.
     */
    private function auditSnapshot(int $id): array
    {
        $r = (array) (DB::table('repository')->where('id', $id)
            ->select('identifier', 'desc_status_id', 'desc_detail_id', 'desc_identifier', 'upload_limit')
            ->first() ?? []);
        $a = (array) (DB::table('actor')->where('id', $id)
            ->select('icip_sensitivity')
            ->first() ?? []);
        $i18n = (array) (DB::table('actor_i18n')->where('id', $id)
            ->where('culture', $this->culture)
            ->select('authorized_form_of_name', 'history', 'general_context', 'mandates', 'rules')
            ->first() ?? []);
        $rep_i18n = (array) (DB::table('repository_i18n')->where('id', $id)
            ->where('culture', $this->culture)
            ->first() ?? []);
        unset($rep_i18n['id'], $rep_i18n['culture'], $rep_i18n['created_at'], $rep_i18n['updated_at']);
        return array_merge($r, $a, $i18n, $rep_i18n);
    }

    public function update(int $id, array $data): void
    {
        $auditBefore = $this->auditSnapshot($id);

        DB::transaction(function () use ($id, $data) {
            // 1. Update repository record
            $repoUpdate = [];
            foreach (['identifier', 'desc_status_id', 'desc_detail_id', 'desc_identifier', 'upload_limit'] as $field) {
                if (array_key_exists($field, $data)) {
                    $repoUpdate[$field] = $data[$field];
                }
            }
            if (!empty($repoUpdate)) {
                DB::table('repository')->where('id', $id)->update($repoUpdate);
            }

            // 1b. Update actor record (ICIP cultural-sensitivity URI lives on
            // actor for repository entities — class-table-inheritance parent).
            if (array_key_exists('icip_sensitivity', $data)) {
                $value = $data['icip_sensitivity'];
                if ($value === '') {
                    $value = null;
                }
                DB::table('actor')->where('id', $id)->update(['icip_sensitivity' => $value]);
            }

            // 2. Update actor_i18n (ISAAR fields) — upsert
            $actorI18nFields = [
                'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
                'legal_status', 'functions', 'mandates', 'internal_structures',
                'general_context', 'institution_responsible_identifier', 'rules',
                'sources', 'revision_history',
            ];
            $actorI18n = [];
            foreach ($actorI18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $actorI18n[$field] = $data[$field];
                }
            }
            if (!empty($actorI18n)) {
                // Issue #61 Phase 3c: snapshot before, run upsert, detect overrides.
                $beforeActorI18n = (array) (DB::table('actor_i18n')
                    ->where('id', $id)->where('culture', $this->culture ?? 'en')
                    ->first(array_keys($actorI18n)) ?? []);
                $this->upsertI18n('actor_i18n', $id, $actorI18n);
                try {
                    app(\AhgProvenanceAi\Services\OverrideService::class)
                        ->detectOverridesFromForm('actor', (int) $id, $beforeActorI18n, $actorI18n, (int) (auth()->id() ?? 0));
                } catch (\Throwable $e) { \Log::warning('RepositoryService update (actor_i18n): override detection failed: ' . $e->getMessage()); }
            }

            // 3. Update repository_i18n (ISDIAH fields) — upsert
            $repoI18nFields = [
                'geocultural_context', 'collecting_policies', 'buildings', 'holdings',
                'finding_aids', 'opening_times', 'access_conditions', 'disabled_access',
                'research_services', 'reproduction_services', 'public_facilities',
                'desc_institution_identifier', 'desc_rules', 'desc_sources', 'desc_revision_history',
            ];
            $repoI18n = [];
            foreach ($repoI18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $repoI18n[$field] = $data[$field];
                }
            }
            if (!empty($repoI18n)) {
                // Issue #61 Phase 3c: snapshot before, run upsert, detect overrides.
                $beforeRepoI18n = (array) (DB::table('repository_i18n')
                    ->where('id', $id)->where('culture', $this->culture ?? 'en')
                    ->first(array_keys($repoI18n)) ?? []);
                $this->upsertI18n('repository_i18n', $id, $repoI18n);
                try {
                    app(\AhgProvenanceAi\Services\OverrideService::class)
                        ->detectOverridesFromForm('repository', (int) $id, $beforeRepoI18n, $repoI18n, (int) (auth()->id() ?? 0));
                } catch (\Throwable $e) { \Log::warning('RepositoryService update (repository_i18n): override detection failed: ' . $e->getMessage()); }
            }

            // 4. Sync parallel name(s) (other_name table, type_id 148)
            if (array_key_exists('parallel_name', $data)) {
                $this->syncOtherName($id, $data['parallel_name'], 148);
            }

            // 5. Sync other name(s) (other_name table, type_id 149)
            if (array_key_exists('other_name', $data)) {
                $this->syncOtherName($id, $data['other_name'], 149);
            }

            // 6. Sync maintenance notes (note table, type_id 174)
            if (array_key_exists('maintenance_notes', $data)) {
                $this->syncMaintenanceNotes($id, $data['maintenance_notes']);
            }

            // 7. Sync contacts if provided
            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($id, $data['contacts']);
            }

            // 5. Touch the object record
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });

        $auditAfter = $this->auditSnapshot($id);
        \AhgCore\Support\AuditLog::captureEdit($id, 'repository', $auditBefore, $auditAfter);
    }

    /**
     * Delete a repository and all related data.
     */
    public function delete(int $id): void
    {
        \AhgCore\Support\AuditLog::captureDelete($id, 'repository', $this->auditSnapshot($id));

        DB::transaction(function () use ($id) {
            // 1. Delete contact information
            $contactIds = DB::table('contact_information')->where('actor_id', $id)->pluck('id')->toArray();
            if (!empty($contactIds)) {
                DB::table('contact_information_i18n')->whereIn('id', $contactIds)->delete();
                DB::table('contact_information')->whereIn('id', $contactIds)->delete();
            }

            // 2. Delete relations
            $relationIds = DB::table('relation')
                ->where('subject_id', $id)->orWhere('object_id', $id)
                ->pluck('id')->toArray();
            if (!empty($relationIds)) {
                DB::table('relation_i18n')->whereIn('id', $relationIds)->delete();
                DB::table('relation')->whereIn('id', $relationIds)->delete();
                DB::table('slug')->whereIn('object_id', $relationIds)->delete();
                DB::table('object')->whereIn('id', $relationIds)->delete();
            }

            // 3. Delete other names (parallel, other forms)
            $otherNameIds = DB::table('other_name')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($otherNameIds)) {
                DB::table('other_name_i18n')->whereIn('id', $otherNameIds)->delete();
                DB::table('other_name')->whereIn('id', $otherNameIds)->delete();
            }

            // 4. Delete notes
            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            // 4. Delete term relations (access points)
            DB::table('object_term_relation')->where('object_id', $id)->delete();

            // 5. Delete repository_i18n
            DB::table('repository_i18n')->where('id', $id)->delete();

            // 6. Delete actor_i18n
            DB::table('actor_i18n')->where('id', $id)->delete();

            // 7. Delete repository record
            DB::table('repository')->where('id', $id)->delete();

            // 8. Delete actor record
            DB::table('actor')->where('id', $id)->delete();

            // 9. Delete slug + object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Get the slug for a repository ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    /**
     * Upsert an i18n record.
     */
    protected function upsertI18n(string $table, int $id, array $data): void
    {
        $exists = DB::table($table)
            ->where('id', $id)
            ->where('culture', $this->culture)
            ->exists();

        if ($exists) {
            DB::table($table)->where('id', $id)->where('culture', $this->culture)->update($data);
        } else {
            DB::table($table)->insert(array_merge(['id' => $id, 'culture' => $this->culture], $data));
        }
    }

    /**
     * Save contacts for a new repository.
     */
    protected function saveContacts(int $repoId, array $contacts): void
    {
        foreach ($contacts as $contactData) {
            if ($this->isContactEmpty($contactData)) {
                continue;
            }

            $contactId = DB::table('contact_information')->insertGetId([
                'actor_id' => $repoId,
                'primary_contact' => !empty($contactData['primary_contact']) ? 1 : 0,
                'contact_person' => $contactData['contact_person'] ?? null,
                'street_address' => $contactData['street_address'] ?? null,
                'website' => $contactData['website'] ?? null,
                'email' => $contactData['email'] ?? null,
                'telephone' => $contactData['telephone'] ?? null,
                'fax' => $contactData['fax'] ?? null,
                'postal_code' => $contactData['postal_code'] ?? null,
                'country_code' => $contactData['country_code'] ?? null,
                'longitude' => $contactData['longitude'] ?? null,
                'latitude' => $contactData['latitude'] ?? null,
                'contact_note' => $contactData['contact_note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
                'source_culture' => $this->culture,
                'serial_number' => 0,
            ]);

            DB::table('contact_information_i18n')->insert([
                'id' => $contactId,
                'culture' => $this->culture,
                'contact_type' => $contactData['contact_type'] ?? null,
                'city' => $contactData['city'] ?? null,
                'region' => $contactData['region'] ?? null,
                'note' => $contactData['note'] ?? null,
            ]);
        }
    }

    /**
     * Sync contacts for an existing repository.
     */
    protected function syncContacts(int $repoId, array $contacts): void
    {
        foreach ($contacts as $contactData) {
            if (!empty($contactData['delete']) && !empty($contactData['id'])) {
                DB::table('contact_information_i18n')->where('id', $contactData['id'])->delete();
                DB::table('contact_information')->where('id', $contactData['id'])->delete();
                continue;
            }

            if ($this->isContactEmpty($contactData)) {
                continue;
            }

            if (!empty($contactData['id'])) {
                DB::table('contact_information')->where('id', $contactData['id'])->update([
                    'primary_contact' => !empty($contactData['primary_contact']) ? 1 : 0,
                    'contact_person' => $contactData['contact_person'] ?? null,
                    'street_address' => $contactData['street_address'] ?? null,
                    'website' => $contactData['website'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'telephone' => $contactData['telephone'] ?? null,
                    'fax' => $contactData['fax'] ?? null,
                    'postal_code' => $contactData['postal_code'] ?? null,
                    'country_code' => $contactData['country_code'] ?? null,
                    'longitude' => $contactData['longitude'] ?? null,
                    'latitude' => $contactData['latitude'] ?? null,
                    'contact_note' => $contactData['contact_note'] ?? null,
                    'updated_at' => now(),
                    'serial_number' => DB::raw('serial_number + 1'),
                ]);

                $this->upsertI18n('contact_information_i18n', $contactData['id'], [
                    'contact_type' => $contactData['contact_type'] ?? null,
                    'city' => $contactData['city'] ?? null,
                    'region' => $contactData['region'] ?? null,
                    'note' => $contactData['note'] ?? null,
                ]);
            } else {
                $this->saveContacts($repoId, [$contactData]);
            }
        }
    }

    /**
     * Save an other_name record (parallel or other form of name).
     */
    protected function saveOtherName(int $objectId, string $name, int $typeId): void
    {
        if (trim($name) === '') {
            return;
        }

        $otherNameId = DB::table('other_name')->insertGetId([
            'object_id' => $objectId,
            'type_id' => $typeId,
            'source_culture' => $this->culture,
            'serial_number' => 0,
        ]);

        DB::table('other_name_i18n')->insert([
            'id' => $otherNameId,
            'culture' => $this->culture,
            'name' => trim($name),
        ]);
    }

    /**
     * Sync an other_name record (upsert first, delete if empty).
     */
    protected function syncOtherName(int $objectId, ?string $name, int $typeId): void
    {
        // Find existing record of this type
        $existing = DB::table('other_name')
            ->where('object_id', $objectId)
            ->where('type_id', $typeId)
            ->first();

        if (empty(trim($name ?? ''))) {
            // Remove existing if name cleared
            if ($existing) {
                DB::table('other_name_i18n')->where('id', $existing->id)->delete();
                DB::table('other_name')->where('id', $existing->id)->delete();
            }
            return;
        }

        if ($existing) {
            // Upsert i18n
            $exists = DB::table('other_name_i18n')
                ->where('id', $existing->id)
                ->where('culture', $this->culture)
                ->exists();
            if ($exists) {
                DB::table('other_name_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', $this->culture)
                    ->update(['name' => trim($name)]);
            } else {
                DB::table('other_name_i18n')->insert([
                    'id' => $existing->id,
                    'culture' => $this->culture,
                    'name' => trim($name),
                ]);
            }
        } else {
            $this->saveOtherName($objectId, $name, $typeId);
        }
    }

    /**
     * Save maintenance notes (note table, type_id 174).
     */
    protected function saveMaintenanceNotes(int $objectId, string $content): void
    {
        if (trim($content) === '') {
            return;
        }

        // Notes need their own object record in AtoM's schema
        $noteObjId = DB::table('object')->insertGetId([
            'class_name' => 'QubitNote',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);

        DB::table('note')->insert([
            'object_id' => $objectId,
            'type_id' => 174,
            'scope' => null,
            'user_id' => null,
            'id' => $noteObjId,
            'source_culture' => $this->culture,
            'serial_number' => 0,
        ]);

        DB::table('note_i18n')->insert([
            'id' => $noteObjId,
            'culture' => $this->culture,
            'content' => trim($content),
        ]);
    }

    /**
     * Sync maintenance notes (upsert or delete).
     */
    protected function syncMaintenanceNotes(int $objectId, ?string $content): void
    {
        $existing = DB::table('note')
            ->where('object_id', $objectId)
            ->where('type_id', 174)
            ->first();

        if (empty(trim($content ?? ''))) {
            // Remove if cleared
            if ($existing) {
                DB::table('note_i18n')->where('id', $existing->id)->delete();
                DB::table('note')->where('id', $existing->id)->delete();
                DB::table('object')->where('id', $existing->id)->delete();
            }
            return;
        }

        if ($existing) {
            $exists = DB::table('note_i18n')
                ->where('id', $existing->id)
                ->where('culture', $this->culture)
                ->exists();
            if ($exists) {
                DB::table('note_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', $this->culture)
                    ->update(['content' => trim($content)]);
            } else {
                DB::table('note_i18n')->insert([
                    'id' => $existing->id,
                    'culture' => $this->culture,
                    'content' => trim($content),
                ]);
            }
        } else {
            $this->saveMaintenanceNotes($objectId, $content);
        }
    }

    protected function isContactEmpty(array $data): bool
    {
        foreach (['contact_person', 'street_address', 'website', 'email', 'telephone', 'fax', 'city', 'region', 'postal_code', 'country_code', 'contact_type', 'note'] as $field) {
            if (!empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update repository theme settings (background color, HTML snippet, banner, logo).
     */
    public function updateTheme(int $id, array $data, $request = null): void
    {
        DB::transaction(function () use ($id, $data, $request) {
            // Store background_color and html_snippet in the setting table
            // AtoM stores these as settings scoped to the repository
            if (isset($data['backgroundColor'])) {
                $this->upsertSetting($id, 'background_color', $data['backgroundColor']);
            }

            if (isset($data['htmlSnippet'])) {
                $this->upsertSetting($id, 'htmlSnippet', $data['htmlSnippet']);
            }

            // Handle file uploads (banner and logo) if present
            if ($request && $request->hasFile('banner')) {
                $path = $request->file('banner')->store('repository/' . $id, 'uploads');
                $this->upsertSetting($id, 'banner', $path);
            }

            if ($request && $request->hasFile('logo')) {
                $path = $request->file('logo')->store('repository/' . $id, 'uploads');
                $this->upsertSetting($id, 'logo', $path);
            }

            // Touch the object record
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Upsert a setting scoped to a repository.
     */
    protected function upsertSetting(int $repositoryId, string $name, string $value): void
    {
        $existing = DB::table('setting')
            ->where('name', $name)
            ->where('scope', 'repository_' . $repositoryId)
            ->first();

        if ($existing) {
            DB::table('setting_i18n')
                ->where('id', $existing->id)
                ->where('culture', $this->culture)
                ->update(['value' => $value]);
        } else {
            // Create object + setting + setting_i18n
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitSetting',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            DB::table('setting')->insert([
                'id' => $objectId,
                'name' => $name,
                'scope' => 'repository_' . $repositoryId,
                'editable' => 1,
                'deleteable' => 1,
                'source_culture' => $this->culture,
            ]);

            DB::table('setting_i18n')->insert([
                'id' => $objectId,
                'culture' => $this->culture,
                'value' => $value,
            ]);
        }
    }

    /**
     * Get a scoped repository setting value from setting + setting_i18n tables.
     */
    public function getRepositorySetting(int $repositoryId, string $name): ?string
    {
        return DB::table('setting')
            ->join('setting_i18n', function ($j) {
                $j->on('setting.id', '=', 'setting_i18n.id')
                  ->where('setting_i18n.culture', '=', $this->culture);
            })
            ->where('setting.name', $name)
            ->where('setting.scope', 'repository_' . $repositoryId)
            ->value('setting_i18n.value');
    }
}
