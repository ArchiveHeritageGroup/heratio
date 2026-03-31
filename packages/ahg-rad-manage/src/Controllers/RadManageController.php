<?php

/**
 * RadManageController - Controller for Heratio
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


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RAD (Rules for Archival Description) editor.
 * Migrated from sfRadPlugin.
 * Source standard: "RAD version Jul2008"
 */
class RadManageController extends Controller
{
    /**
     * RAD property names stored in the property table.
     */
    private const RAD_PROPERTIES = [
        'editionStatementOfResponsibility',
        'issuingJurisdictionAndDenomination',
        'noteOnPublishersSeries',
        'numberingWithinPublishersSeries',
        'otherTitleInformation',
        'otherTitleInformationOfPublishersSeries',
        'parallelTitleOfPublishersSeries',
        'standardNumber',
        'statementOfCoordinates',
        'statementOfProjection',
        'statementOfResponsibilityRelatingToPublishersSeries',
        'statementOfScaleArchitectural',
        'statementOfScaleCartographic',
        'titleStatementOfResponsibility',
        'titleProperOfPublishersSeries',
        'languageNotes',
    ];

    public function edit(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        // ── Load the information object ──
        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.collection_type_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.institution_responsible_identifier',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            abort(404);
        }

        // ── POST: validate and save ──
        if ($request->isMethod('post')) {
            $request->validate(['title' => 'required|string|max:65535']);

            // Update information_object table
            DB::table('information_object')
                ->where('id', $io->id)
                ->update([
                    'identifier'              => $request->input('identifier'),
                    'level_of_description_id' => $request->input('level_of_description_id') ?: null,
                    'collection_type_id'      => $request->input('collection_type_id') ?: null,
                    'repository_id'           => $request->input('repository_id') ?: null,
                    'description_status_id'   => $request->input('description_status_id') ?: null,
                    'description_detail_id'   => $request->input('description_detail_id') ?: null,
                    'description_identifier'  => $request->input('description_identifier'),
                    'source_standard'         => 'RAD version Jul2008',
                ]);

            if ($request->has('display_standard_id')) {
                DB::table('information_object')
                    ->where('id', $io->id)
                    ->update(['display_standard_id' => $request->input('display_standard_id') ?: null]);
            }

            // Update information_object_i18n table
            DB::table('information_object_i18n')
                ->where('id', $io->id)
                ->where('culture', $culture)
                ->update([
                    'title'                             => $request->input('title'),
                    'alternate_title'                   => $request->input('alternate_title'),
                    'edition'                           => $request->input('edition'),
                    'extent_and_medium'                 => $request->input('extent_and_medium'),
                    'archival_history'                  => $request->input('archival_history'),
                    'acquisition'                       => $request->input('acquisition'),
                    'scope_and_content'                 => $request->input('scope_and_content'),
                    'appraisal'                         => $request->input('appraisal'),
                    'accruals'                          => $request->input('accruals'),
                    'arrangement'                       => $request->input('arrangement'),
                    'access_conditions'                 => $request->input('access_conditions'),
                    'reproduction_conditions'           => $request->input('reproduction_conditions'),
                    'physical_characteristics'          => $request->input('physical_characteristics'),
                    'finding_aids'                      => $request->input('finding_aids'),
                    'location_of_originals'             => $request->input('location_of_originals'),
                    'location_of_copies'                => $request->input('location_of_copies'),
                    'related_units_of_description'      => $request->input('related_units_of_description'),
                    'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
                    'rules'                             => $request->input('rules'),
                    'sources'                           => $request->input('sources'),
                    'revision_history'                  => $request->input('revision_history'),
                ]);

            // RAD-specific properties from property table
            foreach (self::RAD_PROPERTIES as $propName) {
                $this->saveProperty($io->id, $propName, $request->input($propName), $culture);
            }

            // Languages/scripts of material
            $this->saveSerializedProperty($io->id, 'language', $request->input('materialLanguages', []), $culture);
            $this->saveSerializedProperty($io->id, 'script', $request->input('materialScripts', []), $culture);

            // Languages/scripts of description
            $this->saveSerializedProperty($io->id, 'languageOfDescription', $request->input('languagesOfDescription', []), $culture);
            $this->saveSerializedProperty($io->id, 'scriptOfDescription', $request->input('scriptsOfDescription', []), $culture);

            // Events (dates with type, actor, place)
            if ($request->has('_eventsIncluded')) {
                $eventData = $request->input('events', []);
                $existingEventIds = DB::table('event')->where('object_id', $io->id)->pluck('id');
                if ($existingEventIds->isNotEmpty()) {
                    DB::table('event_i18n')->whereIn('id', $existingEventIds)->delete();
                    DB::table('event')->where('object_id', $io->id)->delete();
                    DB::table('object')->whereIn('id', $existingEventIds)->delete();
                }
                foreach ($eventData as $evt) {
                    if (empty($evt['type_id'])) continue;
                    $eventObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitEvent',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('event')->insert([
                        'id'             => $eventObjectId,
                        'object_id'      => $io->id,
                        'type_id'        => (int) $evt['type_id'],
                        'actor_id'       => !empty($evt['actor_id']) ? (int) $evt['actor_id'] : null,
                        'start_date'     => $evt['start_date'] ?? null,
                        'end_date'       => $evt['end_date'] ?? null,
                        'source_culture' => $culture,
                    ]);
                    DB::table('event_i18n')->insert([
                        'id'      => $eventObjectId,
                        'culture' => $culture,
                        'date'    => $evt['date_display'] ?? null,
                        'name'    => $evt['event_name'] ?? null,
                    ]);
                }
            }

            // Subject access points (taxonomy 35)
            if ($request->has('subjectAccessPointIds')) {
                DB::table('object_term_relation')
                    ->where('object_id', $io->id)
                    ->whereIn('term_id', function ($q) {
                        $q->select('id')->from('term')->where('taxonomy_id', 35);
                    })
                    ->delete();
                foreach (array_filter((array) $request->input('subjectAccessPointIds', [])) as $termId) {
                    DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
                }
            }

            // Place access points (taxonomy 42)
            if ($request->has('placeAccessPointIds')) {
                DB::table('object_term_relation')
                    ->where('object_id', $io->id)
                    ->whereIn('term_id', function ($q) {
                        $q->select('id')->from('term')->where('taxonomy_id', 42);
                    })
                    ->delete();
                foreach (array_filter((array) $request->input('placeAccessPointIds', [])) as $termId) {
                    DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
                }
            }

            // Genre access points (taxonomy 78)
            if ($request->has('genreAccessPointIds')) {
                DB::table('object_term_relation')
                    ->where('object_id', $io->id)
                    ->whereIn('term_id', function ($q) {
                        $q->select('id')->from('term')->where('taxonomy_id', 78);
                    })
                    ->delete();
                foreach (array_filter((array) $request->input('genreAccessPointIds', [])) as $termId) {
                    DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
                }
            }

            // Name access points (relation type 161)
            if ($request->has('nameAccessPointIds')) {
                DB::table('relation')->where('subject_id', $io->id)->where('type_id', 161)->delete();
                foreach (array_filter((array) $request->input('nameAccessPointIds', [])) as $actorId) {
                    $relObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitRelation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('relation')->insert([
                        'id'             => $relObjectId,
                        'subject_id'     => $io->id,
                        'object_id'      => (int) $actorId,
                        'type_id'        => 161,
                        'source_culture' => $culture,
                    ]);
                }
            }

            // Material type (taxonomy 50 — RAD-specific)
            if ($request->has('materialTypeIds')) {
                DB::table('object_term_relation')
                    ->where('object_id', $io->id)
                    ->whereIn('term_id', function ($q) {
                        $q->select('id')->from('term')->where('taxonomy_id', 50);
                    })
                    ->delete();
                foreach (array_filter((array) $request->input('materialTypeIds', [])) as $termId) {
                    DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
                }
            }

            // Related material descriptions (relation type 173)
            if ($request->has('relatedMaterialDescriptionIds')) {
                DB::table('relation')->where('subject_id', $io->id)->where('type_id', 173)->delete();
                foreach (array_filter((array) $request->input('relatedMaterialDescriptionIds', [])) as $relId) {
                    $relObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitRelation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('relation')->insert([
                        'id'             => $relObjectId,
                        'subject_id'     => $io->id,
                        'object_id'      => (int) $relId,
                        'type_id'        => 173,
                        'source_culture' => $culture,
                    ]);
                }
            }

            // Alternative identifiers
            if ($request->has('alternativeIdentifiers')) {
                DB::table('property')
                    ->where('object_id', $io->id)
                    ->where('name', 'alternativeIdentifiers')
                    ->delete();
                foreach (array_filter((array) $request->input('alternativeIdentifiers', [])) as $altId) {
                    $propId = DB::table('property')->insertGetId([
                        'object_id'      => $io->id,
                        'name'           => 'alternativeIdentifiers',
                        'source_culture' => $culture,
                    ]);
                    DB::table('property_i18n')->insert([
                        'id'      => $propId,
                        'culture' => $culture,
                        'value'   => $altId,
                    ]);
                }
            }

            // Publication status
            if ($request->has('publication_status_id')) {
                DB::table('status')->updateOrInsert(
                    ['object_id' => $io->id, 'type_id' => 158],
                    ['status_id' => $request->input('publication_status_id'), 'source_culture' => $culture]
                );
            }

            DB::table('object')->where('id', $io->id)->update(['updated_at' => now()]);

            return redirect()->route('ahgradmanage.edit', ['slug' => $slug])
                ->with('success', 'Description saved (RAD).');
        }

        // ── GET: load related data for the form ──
        $dropdowns = $this->getFormDropdowns($culture);

        // Events (dates with type, actor)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();
        foreach ($events as $evt) {
            $evt->actor_name = $evt->actor_id
                ? DB::table('actor_i18n')->where('id', $evt->actor_id)->where('culture', $culture)->value('authorized_form_of_name')
                : null;
        }

        // Notes
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();
        $publicationNotes = $notes->where('type_id', 220)->values();
        $archivistNotes   = $notes->where('type_id', 174)->values();
        $generalNotes     = $notes->whereNotIn('type_id', [220, 174])->values();

        // Subject access points (taxonomy 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Place access points (taxonomy 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Genre access points (taxonomy 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Name access points (relation type 161)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $culture)
            ->select('relation.object_id as actor_id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Material type terms (taxonomy 50 — RAD-specific)
        $materialTypes = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 50)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Alternative identifiers (property table)
        $alternativeIdentifiers = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'alternativeIdentifiers')
            ->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')
            ->get();

        // Publication status
        $publicationStatusId = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
        }

        // Languages/scripts of material
        $materialLanguages = $this->loadSerializedProperty($io->id, 'language', $culture);
        $materialScripts   = $this->loadSerializedProperty($io->id, 'script', $culture);

        // Languages/scripts of description
        $languagesOfDescription = $this->loadSerializedProperty($io->id, 'languageOfDescription', $culture);
        $scriptsOfDescription   = $this->loadSerializedProperty($io->id, 'scriptOfDescription', $culture);

        // RAD-specific properties
        $radProperties = [];
        foreach (self::RAD_PROPERTIES as $propName) {
            $radProperties[$propName] = $this->loadProperty($io->id, $propName, $culture);
        }

        // Related material descriptions
        $relatedMaterialDescriptions = collect();
        try {
            $relatedMaterialDescriptions = DB::table('relation')
                ->join('information_object_i18n', 'relation.object_id', '=', 'information_object_i18n.id')
                ->join('slug', 'relation.object_id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)
                ->where('relation.type_id', 173)
                ->where('information_object_i18n.culture', $culture)
                ->select('relation.object_id as id', 'information_object_i18n.title', 'slug.slug')
                ->get();
        } catch (\Exception $e) {}

        // Parent info
        $parentTitle = null;
        $parentSlug  = null;
        if ($io->parent_id && $io->parent_id != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $io->parent_id)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object_i18n.title', 'slug.slug')
                ->first();
            if ($parent) {
                $parentTitle = $parent->title;
                $parentSlug  = $parent->slug;
            }
        }

        return view('rad-manage::edit', array_merge(
            [
                'io'                          => $io,
                'events'                      => $events,
                'notes'                       => $generalNotes,
                'publicationNotes'            => $publicationNotes,
                'archivistNotes'              => $archivistNotes,
                'subjects'                    => $subjects,
                'places'                      => $places,
                'genres'                      => $genres,
                'nameAccessPoints'            => $nameAccessPoints,
                'materialTypes'               => $materialTypes,
                'alternativeIdentifiers'      => $alternativeIdentifiers,
                'publicationStatusId'         => $publicationStatusId,
                'materialLanguages'           => $materialLanguages,
                'materialScripts'             => $materialScripts,
                'languagesOfDescription'      => $languagesOfDescription,
                'scriptsOfDescription'        => $scriptsOfDescription,
                'radProperties'               => $radProperties,
                'relatedMaterialDescriptions' => $relatedMaterialDescriptions,
                'parentTitle'                 => $parentTitle,
                'parentSlug'                  => $parentSlug,
            ],
            $dropdowns
        ));
    }

    // ── Helper: form dropdown queries ──

    private function getFormDropdowns(string $culture): array
    {
        $levels = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        $descriptionStatuses = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 44)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        $descriptionDetails = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 43)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        $displayStandards = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 52)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        $eventTypes = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 40)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Material type options (taxonomy 50 — RAD-specific)
        $materialTypeOptions = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 50)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        return compact('levels', 'repositories', 'descriptionStatuses', 'descriptionDetails', 'displayStandards', 'eventTypes', 'materialTypeOptions');
    }

    // ── Helper: property load/save ──

    private function loadProperty(int $objectId, string $name, string $culture): ?string
    {
        return DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $objectId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
    }

    private function saveProperty(int $objectId, string $name, ?string $value, string $culture): void
    {
        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            DB::table('property_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);
        } elseif ($value !== null && $value !== '') {
            $propId = DB::table('property')->insertGetId([
                'object_id'      => $objectId,
                'name'           => $name,
                'source_culture' => $culture,
            ]);
            DB::table('property_i18n')->insert([
                'id'      => $propId,
                'culture' => $culture,
                'value'   => $value,
            ]);
        }
    }

    private function loadSerializedProperty(int $objectId, string $name, string $culture): \Illuminate\Support\Collection
    {
        $raw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $objectId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');

        if ($raw) {
            $decoded = @unserialize($raw);
            if (is_array($decoded)) {
                return collect($decoded);
            }
        }

        return collect();
    }

    private function saveSerializedProperty(int $objectId, string $name, array $values, string $culture): void
    {
        $serialized = serialize(array_values(array_filter($values)));

        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            DB::table('property_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $serialized]);
        } elseif (!empty($values)) {
            $propId = DB::table('property')->insertGetId([
                'object_id'      => $objectId,
                'name'           => $name,
                'source_culture' => $culture,
            ]);
            DB::table('property_i18n')->insert([
                'id'      => $propId,
                'culture' => $culture,
                'value'   => $serialized,
            ]);
        }
    }
}
