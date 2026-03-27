<?php

namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActorService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Get an actor by slug with all related data for show/edit.
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
     * Get an actor by ID with all ISAAR(CPF) fields.
     */
    public function getById(int $id): ?object
    {
        $actor = DB::table('actor')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->where('actor.id', $id)
            ->where('object.class_name', 'QubitActor')
            ->select([
                'actor.id',
                'actor.entity_type_id',
                'actor.description_status_id',
                'actor.description_detail_id',
                'actor.description_identifier',
                'actor.source_standard',
                'actor.corporate_body_identifiers',
                'actor.parent_id',
                'actor.source_culture',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'actor_i18n.places',
                'actor_i18n.legal_status',
                'actor_i18n.functions',
                'actor_i18n.mandates',
                'actor_i18n.internal_structures',
                'actor_i18n.general_context',
                'actor_i18n.institution_responsible_identifier',
                'actor_i18n.rules',
                'actor_i18n.sources',
                'actor_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'object.serial_number',
                'slug.slug',
            ])
            ->first();

        return $actor;
    }

    /**
     * Resolve entity type name from term_i18n.
     */
    public function getEntityTypeName(?int $typeId): ?string
    {
        if (!$typeId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $typeId)
            ->where('culture', $this->culture)
            ->value('name');
    }

    /**
     * Get other names (parallel, standardized, other forms).
     */
    public function getOtherNames(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', $this->culture);
            })
            ->where('other_name.object_id', $actorId)
            ->select(
                'other_name.id',
                'other_name.type_id',
                'other_name.start_date',
                'other_name.end_date',
                'other_name_i18n.name',
                'other_name_i18n.note',
                'other_name_i18n.dates'
            )
            ->get();
    }

    /**
     * Get contact information for an actor.
     */
    public function getContacts(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('contact_information_extended', 'contact_information.id', '=', 'contact_information_extended.contact_information_id')
            ->where('contact_information.actor_id', $actorId)
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
                'contact_information.serial_number',
                'contact_information_i18n.contact_type',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
                'contact_information_i18n.note',
                // Extended contact fields
                'contact_information_extended.title',
                'contact_information_extended.role',
                'contact_information_extended.department',
                'contact_information_extended.cell',
                'contact_information_extended.id_number',
                'contact_information_extended.alternative_email',
                'contact_information_extended.alternative_phone',
                'contact_information_extended.preferred_contact_method',
                'contact_information_extended.language_preference',
                'contact_information_extended.notes as extended_notes',
            ])
            ->get();
    }

    /**
     * Get events (dates) for an actor.
     */
    public function getEvents(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', '=', $this->culture);
            })
            ->where('event.actor_id', $actorId)
            ->select(
                'event.id',
                'event.type_id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name'
            )
            ->get();
    }

    /**
     * Get related authority records (actor-to-actor relations).
     */
    public function getRelatedActors(int $actorId): array
    {
        $asSubject = DB::table('relation')
            ->join('actor', 'relation.object_id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('relation_i18n', function ($j) {
                $j->on('relation.id', '=', 'relation_i18n.id')
                    ->where('relation_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $actorId)
            ->where('object.class_name', 'QubitActor')
            ->select(
                'relation.id as relation_id',
                'relation.type_id',
                'relation.start_date',
                'relation.end_date',
                'actor.id',
                'actor.description_identifier as identifier',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.dates_of_existence',
                'relation_i18n.description as relation_description',
                'relation_i18n.date as relation_date',
                'slug.slug'
            )
            ->get()
            ->all();

        $asObject = DB::table('relation')
            ->join('actor', 'relation.subject_id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('relation_i18n', function ($j) {
                $j->on('relation.id', '=', 'relation_i18n.id')
                    ->where('relation_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->where('relation.object_id', $actorId)
            ->where('object.class_name', 'QubitActor')
            ->select(
                'relation.id as relation_id',
                'relation.type_id',
                'relation.start_date',
                'relation.end_date',
                'actor.id',
                'actor.description_identifier as identifier',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.dates_of_existence',
                'relation_i18n.description as relation_description',
                'relation_i18n.date as relation_date',
                'slug.slug'
            )
            ->get()
            ->all();

        return array_merge($asSubject, $asObject);
    }

    /**
     * Get information objects related to this actor (via events).
     */
    public function getRelatedResources(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('event')
            ->join('information_object', 'event.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('event.actor_id', $actorId)
            ->where('information_object.id', '!=', 1)
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'information_object.identifier',
                'event.type_id as event_type_id',
                'slug.slug'
            )
            ->distinct()
            ->get();
    }

    /**
     * Get ISDF functions related to this actor.
     */
    public function getRelatedFunctions(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('function_object', 'relation.object_id', '=', 'function_object.id')
            ->join('object', 'function_object.id', '=', 'object.id')
            ->leftJoin('function_i18n', function ($j) {
                $j->on('function_object.id', '=', 'function_i18n.id')
                    ->where('function_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $actorId)
            ->where('object.class_name', 'QubitFunction')
            ->select(
                'function_object.id',
                'function_i18n.authorized_form_of_name as name',
                'slug.slug'
            )
            ->get();
    }

    /**
     * Get maintenance notes (stored in note table).
     */
    public function getMaintenanceNotes(int $actorId): ?string
    {
        // Maintenance note type_id = 174 (MAINTENANCE_NOTE_ID)
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $actorId)
            ->where('note.type_id', 174)
            ->where('note_i18n.culture', $this->culture)
            ->value('note_i18n.content');
    }

    /**
     * Get subject access points for this actor.
     */
    public function getSubjectAccessPoints(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('object_term_relation.object_id', $actorId)
            ->where('term.taxonomy_id', 35) // Subject taxonomy
            ->select('term.id', 'term_i18n.name')
            ->get();
    }

    /**
     * Get place access points for this actor.
     */
    public function getPlaceAccessPoints(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('object_term_relation.object_id', $actorId)
            ->where('term.taxonomy_id', 42) // Place taxonomy
            ->select('term.id', 'term_i18n.name')
            ->get();
    }

    /**
     * Get occupation access points for this actor.
     */
    public function getOccupations(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('object_term_relation.object_id', $actorId)
            ->where('term.taxonomy_id', 78) // Occupation taxonomy (same as genre for IOs)
            ->select('object_term_relation.id as relation_id', 'term.id', 'term_i18n.name', 'slug.slug')
            ->get();
    }

    /**
     * Get occupation notes keyed by object_term_relation ID.
     * Notes are stored in the note table with type_id = 188 (ACTOR_OCCUPATION_NOTE_ID),
     * where note.object_id = object_term_relation.id.
     */
    public function getOccupationNotes(int $actorId): array
    {
        $relationIds = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $actorId)
            ->where('term.taxonomy_id', 78)
            ->pluck('object_term_relation.id')
            ->toArray();

        if (empty($relationIds)) {
            return [];
        }

        return DB::table('note')
            ->leftJoin('note_i18n', function ($j) {
                $j->on('note.id', '=', 'note_i18n.id')
                    ->where('note_i18n.culture', '=', $this->culture);
            })
            ->where('note.type_id', 188) // ACTOR_OCCUPATION_NOTE_ID
            ->whereIn('note.object_id', $relationIds)
            ->select('note.object_id as relation_id', 'note_i18n.content')
            ->get()
            ->keyBy('relation_id')
            ->map(fn ($row) => $row->content)
            ->toArray();
    }

    /**
     * Get information objects where this actor is a name access point ("Subject of").
     * Uses the relation table with type_id = 161 (name access points),
     * where relation.object_id = actor.id and relation.subject_id = information_object.id.
     */
    public function getSubjectOfResources(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $actorId)
            ->where('relation.type_id', 161) // name access points
            ->where('information_object.id', '!=', 1) // exclude root
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->distinct()
            ->get();
    }

    /**
     * Get language(s) from property table.
     */
    public function getLanguages(int $actorId): array
    {
        $row = DB::table('property')
            ->leftJoin('property_i18n', function ($j) {
                $j->on('property.id', '=', 'property_i18n.id')
                    ->where('property_i18n.culture', '=', $this->culture);
            })
            ->where('property.object_id', $actorId)
            ->where('property.name', 'language')
            ->value('property_i18n.value');

        if (!$row) {
            return [];
        }

        // AtoM stores as serialized PHP array
        $decoded = @unserialize($row);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [$row];
    }

    /**
     * Get script(s) from property table.
     */
    public function getScripts(int $actorId): array
    {
        $row = DB::table('property')
            ->leftJoin('property_i18n', function ($j) {
                $j->on('property.id', '=', 'property_i18n.id')
                    ->where('property_i18n.culture', '=', $this->culture);
            })
            ->where('property.object_id', $actorId)
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
     * Get the maintaining repository for this actor.
     * In AtoM, this is stored as a relation where the actor is the subject
     * and the repository is the object, with type_id from the Relation Type taxonomy.
     */
    public function getMaintainingRepository(int $actorId): ?object
    {
        return DB::table('relation')
            ->join('repository', 'relation.object_id', '=', 'repository.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'repository.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $actorId)
            ->where('object.class_name', 'QubitRepository')
            ->select(
                'repository.id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug'
            )
            ->first();
    }

    /**
     * Get the relation category name (parent term) for a given relation type_id.
     */
    public function getRelationCategoryNames(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $result = [];
        foreach ($typeIds as $typeId) {
            $parentId = DB::table('term')->where('id', $typeId)->value('parent_id');
            if ($parentId) {
                $categoryName = DB::table('term_i18n')
                    ->where('id', $parentId)
                    ->where('culture', $this->culture)
                    ->value('name');
                if ($categoryName) {
                    $result[$typeId] = $categoryName;
                }
            }
        }

        return $result;
    }

    /**
     * Get digital objects for this actor.
     */
    public function getDigitalObjects(int $actorId): array
    {
        return \AhgCore\Services\DigitalObjectService::getForObject($actorId);
    }

    /**
     * Get form dropdown choices for actor edit/create forms.
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
            'entityTypes' => $termLookup(32),          // Actor Entity Types
            'descriptionStatuses' => $termLookup(33),  // Description Statuses
            'descriptionDetails' => $termLookup(31),   // Description Detail Levels
            'nameTypes' => $termLookup(36),            // Actor Name Types
            'relationTypes' => $termLookup(55),        // Actor Relation Type
        ];
    }

    /**
     * Get relation type names (batch lookup).
     */
    public function getRelationTypeNames(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        return DB::table('term_i18n')
            ->whereIn('id', $typeIds)
            ->where('culture', $this->culture)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get converse relation type names.
     *
     * In AtoM's actor relation taxonomy (55), relation types come in pairs
     * under a shared parent (e.g. "is the superior of" / "is the subordinate of").
     * The converse of a type is the other term with the same parent.
     * For self-referential types (e.g. "is the sibling of"), the converse is itself.
     */
    public function getConverseRelationTypeNames(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $result = [];
        foreach ($typeIds as $typeId) {
            $term = DB::table('term')->where('id', $typeId)->first();
            if (!$term || !$term->parent_id) {
                continue;
            }

            // Get all siblings under the same parent in taxonomy 55 (Actor relation type)
            $siblings = DB::table('term')
                ->join('term_i18n', function ($j) {
                    $j->on('term.id', '=', 'term_i18n.id')
                        ->where('term_i18n.culture', '=', $this->culture);
                })
                ->where('term.parent_id', $term->parent_id)
                ->where('term.taxonomy_id', 55)
                ->where('term.id', '!=', $typeId)
                ->select('term.id', 'term_i18n.name')
                ->orderBy('term.id')
                ->get();

            if ($siblings->isNotEmpty()) {
                // Pick the first sibling as the converse
                $result[$typeId] = $siblings->first()->name;
            }
        }

        return $result;
    }

    /**
     * Get name type names (batch lookup).
     */
    public function getNameTypeNames(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        return DB::table('term_i18n')
            ->whereIn('id', $typeIds)
            ->where('culture', $this->culture)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Create a new actor with all related data.
     *
     * @return int The new actor ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitActor',
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
            DB::table('slug')->insert([
                'object_id' => $id,
                'slug' => $slug,
            ]);

            // 3. Create actor record
            DB::table('actor')->insert([
                'id' => $id,
                'entity_type_id' => $data['entity_type_id'] ?? null,
                'description_status_id' => $data['description_status_id'] ?? null,
                'description_detail_id' => $data['description_detail_id'] ?? null,
                'description_identifier' => $data['description_identifier'] ?? null,
                'source_standard' => $data['source_standard'] ?? null,
                'corporate_body_identifiers' => $data['corporate_body_identifiers'] ?? null,
                'parent_id' => 3, // QubitActor::ROOT_ID
                'source_culture' => $this->culture,
            ]);

            // 4. Save actor_i18n (ISAAR fields)
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

            // 5. Save contact information if provided
            if (!empty($data['contacts'])) {
                $this->saveContacts($id, $data['contacts']);
            }

            // 6. Save other names if provided
            if (!empty($data['other_names'])) {
                $this->saveOtherNames($id, $data['other_names']);
            }

            // 7. Save maintenance notes if provided
            if (!empty($data['maintenance_notes'])) {
                $this->saveMaintenanceNote($id, $data['maintenance_notes']);
            }

            return $id;
        });
    }

    /**
     * Update an existing actor.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            // 1. Update actor record
            $actorUpdate = [];
            $actorFields = [
                'entity_type_id', 'description_status_id', 'description_detail_id',
                'description_identifier', 'source_standard', 'corporate_body_identifiers',
            ];
            foreach ($actorFields as $field) {
                if (array_key_exists($field, $data)) {
                    $actorUpdate[$field] = $data[$field];
                }
            }
            if (!empty($actorUpdate)) {
                DB::table('actor')->where('id', $id)->update($actorUpdate);
            }

            // 2. Update actor_i18n (ISAAR fields) — upsert
            $i18nFields = [
                'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
                'legal_status', 'functions', 'mandates', 'internal_structures',
                'general_context', 'institution_responsible_identifier', 'rules',
                'sources', 'revision_history',
            ];
            $i18nData = [];
            foreach ($i18nFields as $field) {
                if (array_key_exists($field, $data)) {
                    $i18nData[$field] = $data[$field];
                }
            }
            if (!empty($i18nData)) {
                $exists = DB::table('actor_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('actor_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18nData);
                } else {
                    DB::table('actor_i18n')->insert(array_merge(
                        ['id' => $id, 'culture' => $this->culture],
                        $i18nData
                    ));
                }
            }

            // 3. Save contacts if provided
            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($id, $data['contacts']);
            }

            // 4. Save other names if provided
            if (array_key_exists('other_names', $data)) {
                $this->syncOtherNames($id, $data['other_names']);
            }

            // 5. Save maintenance notes
            if (array_key_exists('maintenance_notes', $data)) {
                $this->saveMaintenanceNote($id, $data['maintenance_notes']);
            }

            // 6. Touch the object record
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Delete an actor and all related data.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // 1. Delete events where this actor is the creator/subject
            $eventIds = DB::table('event')->where('actor_id', $id)->pluck('id')->toArray();
            if (!empty($eventIds)) {
                DB::table('event_i18n')->whereIn('id', $eventIds)->delete();
                DB::table('event')->whereIn('id', $eventIds)->delete();
                // Delete event object records
                DB::table('slug')->whereIn('object_id', $eventIds)->delete();
                DB::table('object')->whereIn('id', $eventIds)->delete();
            }

            // 2. Delete relations (actor-to-actor, actor-to-function, etc.)
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

            // 3. Delete contact information (including extended data)
            $contactIds = DB::table('contact_information')->where('actor_id', $id)->pluck('id')->toArray();
            if (!empty($contactIds)) {
                DB::table('contact_information_extended')->whereIn('contact_information_id', $contactIds)->delete();
                DB::table('contact_information_i18n')->whereIn('id', $contactIds)->delete();
                DB::table('contact_information')->whereIn('id', $contactIds)->delete();
            }

            // 4. Delete other names
            $otherNameIds = DB::table('other_name')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($otherNameIds)) {
                DB::table('other_name_i18n')->whereIn('id', $otherNameIds)->delete();
                DB::table('other_name')->whereIn('id', $otherNameIds)->delete();
                DB::table('object')->whereIn('id', $otherNameIds)->delete();
            }

            // 5. Delete notes
            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            // 6. Delete term relations (access points)
            DB::table('object_term_relation')->where('object_id', $id)->delete();

            // 7. Delete actor_i18n
            DB::table('actor_i18n')->where('id', $id)->delete();

            // 8. Delete actor record
            DB::table('actor')->where('id', $id)->delete();

            // 9. Delete slug + object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Save contacts for a new actor.
     */
    protected function saveContacts(int $actorId, array $contacts): void
    {
        foreach ($contacts as $contactData) {
            if ($this->isContactEmpty($contactData)) {
                continue;
            }

            $contactId = DB::table('contact_information')->insertGetId([
                'actor_id' => $actorId,
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

            // Save extended contact data if any extended fields are present
            $this->saveExtendedContactData($contactId, $contactData);
        }
    }

    /**
     * Sync contacts for an existing actor (create, update, delete).
     */
    protected function syncContacts(int $actorId, array $contacts): void
    {
        foreach ($contacts as $contactData) {
            // Handle deletion
            if (!empty($contactData['delete']) && !empty($contactData['id'])) {
                $this->deleteExtendedContactData($contactData['id']);
                DB::table('contact_information_i18n')->where('id', $contactData['id'])->delete();
                DB::table('contact_information')->where('id', $contactData['id'])->delete();
                continue;
            }

            if ($this->isContactEmpty($contactData)) {
                continue;
            }

            if (!empty($contactData['id'])) {
                // Update existing contact
                DB::table('contact_information')
                    ->where('id', $contactData['id'])
                    ->update([
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

                $exists = DB::table('contact_information_i18n')
                    ->where('id', $contactData['id'])
                    ->where('culture', $this->culture)
                    ->exists();

                $i18n = [
                    'contact_type' => $contactData['contact_type'] ?? null,
                    'city' => $contactData['city'] ?? null,
                    'region' => $contactData['region'] ?? null,
                    'note' => $contactData['note'] ?? null,
                ];

                if ($exists) {
                    DB::table('contact_information_i18n')
                        ->where('id', $contactData['id'])
                        ->where('culture', $this->culture)
                        ->update($i18n);
                } else {
                    DB::table('contact_information_i18n')->insert(array_merge(
                        ['id' => $contactData['id'], 'culture' => $this->culture],
                        $i18n
                    ));
                }

                // Save extended contact data
                $this->saveExtendedContactData($contactData['id'], $contactData);
            } else {
                // Create new contact
                $this->saveContacts($actorId, [$contactData]);
            }
        }
    }

    /**
     * Save other names for an actor.
     */
    protected function saveOtherNames(int $actorId, array $names): void
    {
        foreach ($names as $nameData) {
            if (empty($nameData['name'])) {
                continue;
            }

            $otherNameId = DB::table('other_name')->insertGetId([
                'object_id' => $actorId,
                'type_id' => $nameData['type_id'] ?? null,
                'start_date' => $nameData['start_date'] ?? null,
                'end_date' => $nameData['end_date'] ?? null,
                'source_culture' => $this->culture,
                'serial_number' => 0,
            ]);

            DB::table('other_name_i18n')->insert([
                'id' => $otherNameId,
                'culture' => $this->culture,
                'name' => $nameData['name'],
                'note' => $nameData['note'] ?? null,
                'dates' => $nameData['dates'] ?? null,
            ]);
        }
    }

    /**
     * Sync other names (delete all existing, re-create from input).
     */
    protected function syncOtherNames(int $actorId, array $names): void
    {
        // Delete existing other names
        $existingIds = DB::table('other_name')->where('object_id', $actorId)->pluck('id')->toArray();
        if (!empty($existingIds)) {
            DB::table('other_name_i18n')->whereIn('id', $existingIds)->delete();
            DB::table('other_name')->whereIn('id', $existingIds)->delete();
        }

        // Re-create from input
        $this->saveOtherNames($actorId, $names);
    }

    /**
     * Save or update maintenance note.
     */
    protected function saveMaintenanceNote(int $actorId, ?string $content): void
    {
        // Delete existing maintenance note
        $existingNoteId = DB::table('note')
            ->where('object_id', $actorId)
            ->where('type_id', 174)
            ->value('id');

        if ($existingNoteId) {
            DB::table('note_i18n')->where('id', $existingNoteId)->delete();
            DB::table('note')->where('id', $existingNoteId)->delete();
            DB::table('object')->where('id', $existingNoteId)->delete();
        }

        if (!empty($content)) {
            $noteObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitNote',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            DB::table('note')->insert([
                'object_id' => $actorId,
                'type_id' => 174,
                'id' => $noteObjectId,
                'source_culture' => $this->culture,
                'user_id' => auth()->id(),
            ]);

            DB::table('note_i18n')->insert([
                'id' => $noteObjectId,
                'culture' => $this->culture,
                'content' => $content,
            ]);
        }
    }

    /**
     * Check if a contact data array is essentially empty.
     */
    protected function isContactEmpty(array $data): bool
    {
        $fields = [
            'contact_person', 'street_address', 'website', 'email',
            'telephone', 'fax', 'city', 'region', 'postal_code',
            'country_code', 'contact_type', 'note', 'contact_note',
            // Extended fields
            'title', 'role', 'department', 'cell', 'id_number',
            'alternative_email', 'alternative_phone', 'preferred_contact_method',
            'language_preference', 'extended_notes',
        ];

        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    // ── Extended contact data (contact_information_extended table) ──────

    private const EXTENDED_CONTACT_FIELDS = [
        'title', 'role', 'department', 'cell', 'id_number',
        'alternative_email', 'alternative_phone', 'preferred_contact_method',
        'language_preference', 'notes',
    ];

    /**
     * Get extended contact data for a given contact_information row.
     */
    public function getExtendedContactData(int $contactId): array
    {
        $row = DB::table('contact_information_extended')
            ->where('contact_information_id', $contactId)
            ->first();

        if (!$row) {
            return [];
        }

        return (array) $row;
    }

    /**
     * Save (insert or update) extended contact data for a contact_information row.
     */
    public function saveExtendedContactData(int $contactId, array $data): void
    {
        $values = [];
        foreach (self::EXTENDED_CONTACT_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($value === '' || $value === null) {
                    $value = null;
                }
                $values[$field] = $value;
            }
        }

        // Map the form field 'extended_notes' to the DB column 'notes'
        if (array_key_exists('extended_notes', $data)) {
            $value = $data['extended_notes'];
            $values['notes'] = ($value === '' || $value === null) ? null : $value;
        }

        if (empty($values)) {
            return;
        }

        $exists = DB::table('contact_information_extended')
            ->where('contact_information_id', $contactId)
            ->exists();

        if ($exists) {
            $values['updated_at'] = now();
            DB::table('contact_information_extended')
                ->where('contact_information_id', $contactId)
                ->update($values);
        } else {
            $values['contact_information_id'] = $contactId;
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('contact_information_extended')->insert($values);
        }
    }

    /**
     * Delete extended contact data for a contact_information row.
     */
    public function deleteExtendedContactData(int $contactId): void
    {
        DB::table('contact_information_extended')
            ->where('contact_information_id', $contactId)
            ->delete();
    }

    /**
     * Get the slug for an actor ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    // ─── AHG Actor Completeness / Identifiers / Occupations ─────────

    /**
     * Known authority source URI patterns (for auto-URI construction).
     */
    public const IDENTIFIER_URI_PATTERNS = [
        'wikidata' => 'https://www.wikidata.org/wiki/%s',
        'viaf'     => 'https://viaf.org/viaf/%s',
        'ulan'     => 'https://vocab.getty.edu/ulan/%s',
        'lcnaf'    => 'https://id.loc.gov/authorities/names/%s',
        'isni'     => 'https://isni.org/isni/%s',
        'orcid'    => 'https://orcid.org/%s',
        'gnd'      => 'https://d-nb.info/gnd/%s',
    ];

    /**
     * ISAAR(CPF) field weights for completeness score calculation.
     */
    public const COMPLETENESS_WEIGHTS = [
        'authorized_name'   => 15,
        'entity_type'       => 5,
        'dates_existence'   => 10,
        'history'           => 10,
        'places'            => 5,
        'legal_status'      => 3,
        'functions'         => 5,
        'mandates'          => 3,
        'internal_struct'   => 3,
        'general_context'   => 3,
        'description_id'    => 3,
        'sources'           => 3,
        'maintenance_notes' => 2,
        'external_ids'      => 10,
        'relations'         => 10,
        'resources'         => 5,
        'contacts'          => 5,
    ];

    /**
     * Completeness level thresholds.
     */
    public const COMPLETENESS_LEVELS = [
        'stub'    => [0, 24],
        'minimal' => [25, 49],
        'partial' => [50, 74],
        'full'    => [75, 100],
    ];

    /**
     * Get completeness record for an actor.
     */
    public function getActorCompleteness(int $actorId): ?object
    {
        return DB::table('ahg_actor_completeness')
            ->where('actor_id', $actorId)
            ->first();
    }

    /**
     * Get all external identifiers for an actor.
     */
    public function getActorIdentifiers(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->orderBy('identifier_type')
            ->get();
    }

    /**
     * Get structured occupations for an actor (from ahg_actor_occupation table).
     */
    public function getActorOccupations(int $actorId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_actor_occupation as o')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('o.term_id', '=', 'ti.id')
                    ->where('ti.culture', '=', $this->culture);
            })
            ->where('o.actor_id', $actorId)
            ->select('o.*', 'ti.name as term_name')
            ->orderBy('o.sort_order')
            ->orderBy('o.date_from')
            ->get();
    }

    /**
     * Calculate and save completeness score for an actor.
     */
    public function saveActorCompleteness(int $actorId, array $data = []): void
    {
        $fieldScores = [];
        $totalWeight = array_sum(self::COMPLETENESS_WEIGHTS);
        $earnedWeight = 0;

        // Fetch actor_i18n data
        $actorI18n = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', $this->culture)
            ->first();

        // Check basic ISAAR fields
        $fieldScores['authorized_name'] = (!empty($actorI18n->authorized_form_of_name)) ? 1 : 0;
        $fieldScores['history'] = (!empty($actorI18n->history)) ? 1 : 0;
        $fieldScores['places'] = (!empty($actorI18n->places)) ? 1 : 0;
        $fieldScores['legal_status'] = (!empty($actorI18n->legal_status)) ? 1 : 0;
        $fieldScores['functions'] = (!empty($actorI18n->functions)) ? 1 : 0;
        $fieldScores['mandates'] = (!empty($actorI18n->mandates)) ? 1 : 0;
        $fieldScores['internal_struct'] = (!empty($actorI18n->internal_structures)) ? 1 : 0;
        $fieldScores['general_context'] = (!empty($actorI18n->general_context)) ? 1 : 0;
        $fieldScores['description_id'] = (!empty($actorI18n->description_identifier)) ? 1 : 0;
        $fieldScores['sources'] = (!empty($actorI18n->sources)) ? 1 : 0;
        $fieldScores['maintenance_notes'] = (!empty($actorI18n->revision_history)) ? 1 : 0;
        $fieldScores['dates_existence'] = (!empty($actorI18n->dates_of_existence)) ? 1 : 0;

        // Check actor entity type
        $actor = DB::table('actor')->where('id', $actorId)->first();
        $fieldScores['entity_type'] = ($actor && !empty($actor->entity_type_id)) ? 1 : 0;

        // Check external identifiers
        $hasIds = DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->exists();
        $fieldScores['external_ids'] = $hasIds ? 1 : 0;

        // Check relations
        $hasRelations = DB::table('relation')
            ->where(function ($q) use ($actorId) {
                $q->where('subject_id', $actorId)
                    ->orWhere('object_id', $actorId);
            })
            ->exists();
        $fieldScores['relations'] = $hasRelations ? 1 : 0;

        // Check linked resources
        $hasResources = DB::table('event')
            ->where('actor_id', $actorId)
            ->exists();
        $fieldScores['resources'] = $hasResources ? 1 : 0;

        // Check contacts
        $hasContacts = DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->exists();
        $fieldScores['contacts'] = $hasContacts ? 1 : 0;

        // Calculate weighted score
        foreach ($fieldScores as $field => $score) {
            if ($score && isset(self::COMPLETENESS_WEIGHTS[$field])) {
                $earnedWeight += self::COMPLETENESS_WEIGHTS[$field];
            }
        }

        $percentage = $totalWeight > 0 ? (int) round(($earnedWeight / $totalWeight) * 100) : 0;
        $level = $this->determineCompletenessLevel($percentage);

        $record = [
            'completeness_level' => $level,
            'completeness_score' => $percentage,
            'field_scores'       => json_encode($fieldScores),
            'has_external_ids'   => $fieldScores['external_ids'],
            'has_relations'      => $fieldScores['relations'],
            'has_resources'      => $fieldScores['resources'],
            'has_contacts'       => $fieldScores['contacts'],
            'scored_at'          => now(),
            'updated_at'         => now(),
        ];

        // Allow manual override fields from data
        if (!empty($data['manual_override'])) {
            $record['manual_override'] = 1;
        }
        if (!empty($data['assigned_to'])) {
            $record['assigned_to'] = $data['assigned_to'];
            $record['assigned_at'] = now();
        }

        $existing = DB::table('ahg_actor_completeness')
            ->where('actor_id', $actorId)
            ->first();

        if ($existing) {
            // Preserve manual override level if set
            if ($existing->manual_override && empty($data['manual_override'])) {
                $record['completeness_level'] = $existing->completeness_level;
            }
            DB::table('ahg_actor_completeness')
                ->where('id', $existing->id)
                ->update($record);
        } else {
            $record['actor_id'] = $actorId;
            $record['created_at'] = now();
            DB::table('ahg_actor_completeness')->insert($record);
        }
    }

    /**
     * Determine completeness level from percentage score.
     */
    protected function determineCompletenessLevel(int $score): string
    {
        foreach (self::COMPLETENESS_LEVELS as $level => $range) {
            if ($score >= $range[0] && $score <= $range[1]) {
                return $level;
            }
        }

        return 'stub';
    }

    /**
     * Save external identifiers for an actor (sync: delete all then re-insert).
     */
    public function saveActorIdentifiers(int $actorId, array $identifiers): void
    {
        // Delete all existing identifiers then re-insert
        DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->delete();

        foreach ($identifiers as $idData) {
            if (empty($idData['identifier_type']) || empty($idData['identifier_value'])) {
                continue;
            }

            $type  = $idData['identifier_type'];
            $value = trim($idData['identifier_value']);

            // Auto-construct URI if not provided
            $uri = $idData['uri'] ?? null;
            if (empty($uri) && isset(self::IDENTIFIER_URI_PATTERNS[$type]) && !empty($value)) {
                $uri = sprintf(self::IDENTIFIER_URI_PATTERNS[$type], $value);
            }

            DB::table('ahg_actor_identifier')->insert([
                'actor_id'         => $actorId,
                'identifier_type'  => $type,
                'identifier_value' => $value,
                'uri'              => $uri,
                'label'            => $idData['label'] ?? null,
                'source'           => $idData['source'] ?? 'manual',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Save structured occupations for an actor (sync: delete all then re-insert).
     */
    public function saveActorOccupations(int $actorId, array $occupations): void
    {
        // Delete all existing occupations then re-insert
        DB::table('ahg_actor_occupation')
            ->where('actor_id', $actorId)
            ->delete();

        foreach ($occupations as $idx => $occData) {
            if (empty($occData['occupation_text']) && empty($occData['term_id'])) {
                continue;
            }

            DB::table('ahg_actor_occupation')->insert([
                'actor_id'        => $actorId,
                'term_id'         => !empty($occData['term_id']) ? (int) $occData['term_id'] : null,
                'occupation_text' => $occData['occupation_text'] ?? null,
                'date_from'       => $occData['date_from'] ?? null,
                'date_to'         => $occData['date_to'] ?? null,
                'notes'           => $occData['notes'] ?? null,
                'sort_order'      => (int) ($occData['sort_order'] ?? $idx),
                'created_at'      => now(),
            ]);
        }
    }
}
