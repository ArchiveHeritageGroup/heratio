<?php

/**
 * ModsManageController - Controller for Heratio
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



namespace AhgModsManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModsManageController extends Controller
{
    /**
     * MODS field names from AtoM sfModsPlugin.
     * Source standard: "MODS version 3.3"
     *
     * MODS fields: accessConditions, identifier, language, subjectAccessPoints,
     * placeAccessPoints, nameAccessPoints, title, type (MODS resource type taxonomy 63),
     * repository, scopeAndContent.
     */

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
            $request->validate([
                'title' => 'required|string|max:65535',
            ]);

            // Update information_object table
            DB::table('information_object')
                ->where('id', $io->id)
                ->update([
                    'identifier'       => $request->input('identifier'),
                    'repository_id'    => $request->input('repository_id') ?: null,
                    'source_standard'  => 'MODS version 3.3',
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
                    'title'            => $request->input('title'),
                    'scope_and_content' => $request->input('scope_and_content'),
                    'access_conditions' => $request->input('access_conditions'),
                ]);

            // Languages of material
            $this->saveSerializedProperty($io->id, 'language', $request->input('materialLanguages', []), $culture);

            // MODS Resource Type (taxonomy 63)
            if ($request->has('modsTypeIds')) {
                DB::table('object_term_relation')
                    ->where('object_id', $io->id)
                    ->whereIn('term_id', function ($q) {
                        $q->select('id')->from('term')->where('taxonomy_id', 63);
                    })
                    ->delete();
                foreach (array_filter((array) $request->input('modsTypeIds', [])) as $termId) {
                    DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
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

            // Publication status
            if ($request->has('publication_status_id')) {
                DB::table('status')->updateOrInsert(
                    ['object_id' => $io->id, 'type_id' => 158],
                    ['status_id' => $request->input('publication_status_id'), 'source_culture' => $culture]
                );
            }

            // Update object.updated_at
            DB::table('object')->where('id', $io->id)->update(['updated_at' => now()]);

            return redirect()->route('ahgmodsmanage.edit', ['slug' => $slug])
                ->with('success', 'Description saved (MODS).');
        }

        // ── GET: load related data for the form ──
        $dropdowns = $this->getFormDropdowns($culture);

        // Events (dates)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();
        foreach ($events as $evt) {
            $evt->actor_name = null;
            if ($evt->actor_id) {
                $evt->actor_name = DB::table('actor_i18n')->where('id', $evt->actor_id)->where('culture', $culture)->value('authorized_form_of_name');
            }
        }

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

        // Name access points (relation type 161)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $culture)
            ->select('relation.object_id as actor_id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // MODS Resource Type terms (taxonomy 63)
        $modsTypes = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 63)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Publication status
        $publicationStatusId = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
        }

        // Languages of material
        $materialLanguages = $this->loadSerializedProperty($io->id, 'language', $culture);

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

        return view('mods-manage::edit', array_merge(
            [
                'io'                  => $io,
                'events'              => $events,
                'subjects'            => $subjects,
                'places'              => $places,
                'nameAccessPoints'    => $nameAccessPoints,
                'modsTypes'           => $modsTypes,
                'publicationStatusId' => $publicationStatusId,
                'materialLanguages'   => $materialLanguages,
                'parentTitle'         => $parentTitle,
                'parentSlug'          => $parentSlug,
            ],
            $dropdowns
        ));
    }

    // ── Helper: form dropdown queries ──

    private function getFormDropdowns(string $culture): array
    {
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
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

        // MODS Resource Type options (taxonomy 63)
        $modsTypeOptions = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 63)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        return compact('repositories', 'displayStandards', 'eventTypes', 'modsTypeOptions');
    }

    // ── Helper: serialized property load/save ──

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
