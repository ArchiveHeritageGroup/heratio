<?php

/**
 * RicManageController - RiC-O (Records in Contexts) description editor.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
 *
 * Cloned from AhgDacsManage\Controllers\DacsManageController (#1425). The
 * cataloguing surface is standard-agnostic AtoM plumbing; the RiC-specific
 * parts are: source_standard = 'RiC-O 1.0', the display-standard dropdown reads
 * taxonomy 70 (NOT the stale 52 the DACS clone still uses), and a RiC-O JSON-LD
 * preview built from the existing ahg/ric engine - guarded so the form still
 * renders if the engine is absent (standalone-install guarantee).
 */

namespace AhgRicManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RicManageController extends Controller
{
    public function edit(Request $request, string $slug)
    {
        $culture = app()->getLocale();

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

        if (! $io) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'title' => 'required|string|max:65535',
            ]);

            DB::table('information_object')
                ->where('id', $io->id)
                ->update([
                    'identifier' => $request->input('identifier'),
                    'level_of_description_id' => $request->input('level_of_description_id') ?: null,
                    'repository_id' => $request->input('repository_id') ?: null,
                    'description_status_id' => $request->input('description_status_id') ?: null,
                    'description_detail_id' => $request->input('description_detail_id') ?: null,
                    'description_identifier' => $request->input('description_identifier'),
                    'source_standard' => config('ric-manage.source_standard', 'RiC-O 1.0'),
                ]);

            if ($request->has('display_standard_id')) {
                DB::table('information_object')
                    ->where('id', $io->id)
                    ->update(['display_standard_id' => $request->input('display_standard_id') ?: null]);
            }

            DB::table('information_object_i18n')
                ->where('id', $io->id)
                ->where('culture', $culture)
                ->update([
                    'title' => $request->input('title'),
                    'alternate_title' => $request->input('alternate_title'),
                    'edition' => $request->input('edition'),
                    'extent_and_medium' => $request->input('extent_and_medium'),
                    'archival_history' => $request->input('archival_history'),
                    'acquisition' => $request->input('acquisition'),
                    'scope_and_content' => $request->input('scope_and_content'),
                    'appraisal' => $request->input('appraisal'),
                    'accruals' => $request->input('accruals'),
                    'arrangement' => $request->input('arrangement'),
                    'access_conditions' => $request->input('access_conditions'),
                    'reproduction_conditions' => $request->input('reproduction_conditions'),
                    'physical_characteristics' => $request->input('physical_characteristics'),
                    'finding_aids' => $request->input('finding_aids'),
                    'location_of_originals' => $request->input('location_of_originals'),
                    'location_of_copies' => $request->input('location_of_copies'),
                    'related_units_of_description' => $request->input('related_units_of_description'),
                    'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
                    'rules' => $request->input('rules'),
                    'sources' => $request->input('sources'),
                    'revision_history' => $request->input('revision_history'),
                ]);

            $this->saveProperty($io->id, 'languageNotes', $request->input('languageNotes'), $culture);
            $this->saveProperty($io->id, 'languageOfDescription', $request->input('languageOfDescription'), $culture);
            $this->saveSerializedProperty($io->id, 'language', $request->input('materialLanguages', []), $culture);
            $this->saveSerializedProperty($io->id, 'script', $request->input('materialScripts', []), $culture);

            // Creators (event type 111)
            if ($request->has('_creatorsIncluded')) {
                $creatorIds = array_filter((array) $request->input('creatorIds', []));
                DB::table('event')->where('object_id', $io->id)->where('type_id', 111)->delete();
                foreach ($creatorIds as $actorId) {
                    $actorId = (int) $actorId;
                    if ($actorId <= 0) {
                        continue;
                    }
                    $eventObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitEvent',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('event')->insert([
                        'id' => $eventObjectId,
                        'object_id' => $io->id,
                        'actor_id' => $actorId,
                        'type_id' => 111,
                        'source_culture' => $culture,
                    ]);
                    DB::table('event_i18n')->insert([
                        'id' => $eventObjectId,
                        'culture' => $culture,
                    ]);
                }
            }

            // Access points (subject 35 / place 42 / genre 78)
            foreach ([['subjectAccessPointIds', 35], ['placeAccessPointIds', 42], ['genreAccessPointIds', 78]] as [$field, $taxonomyId]) {
                if ($request->has($field)) {
                    DB::table('object_term_relation')
                        ->where('object_id', $io->id)
                        ->whereIn('term_id', function ($q) use ($taxonomyId) {
                            $q->select('id')->from('term')->where('taxonomy_id', $taxonomyId);
                        })
                        ->delete();
                    foreach (array_filter((array) $request->input($field, [])) as $termId) {
                        DB::table('object_term_relation')->insert(['object_id' => $io->id, 'term_id' => (int) $termId]);
                    }
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
                        'id' => $relObjectId,
                        'subject_id' => $io->id,
                        'object_id' => (int) $actorId,
                        'type_id' => 161,
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

            DB::table('object')->where('id', $io->id)->update(['updated_at' => now()]);

            // Keep the RiC triplestore in step if the engine is present. Guarded
            // and best-effort - a sync hiccup must never fail the save.
            $this->syncRic($io->id);

            return redirect()->route('ahgricmanage.edit', ['slug' => $slug])
                ->with('success', 'Description saved (RiC-O).');
        }

        // ── GET: load related data for the form ──
        $dropdowns = $this->getFormDropdowns($culture);

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

        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name')
            ->distinct()
            ->get();

        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        $accessPoints = [];
        foreach ([['subjects', 35], ['places', 42], ['genres', 78]] as [$key, $taxonomyId]) {
            $accessPoints[$key] = DB::table('object_term_relation')
                ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
                ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                ->where('object_term_relation.object_id', $io->id)
                ->where('term.taxonomy_id', $taxonomyId)
                ->where('term_i18n.culture', $culture)
                ->select('term.id as term_id', 'term_i18n.name')
                ->get();
        }

        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $culture)
            ->select('relation.object_id as actor_id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        $publicationStatusId = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
        }

        $materialLanguages = $this->loadSerializedProperty($io->id, 'language', $culture);
        $materialScripts = $this->loadSerializedProperty($io->id, 'script', $culture);
        $languageNotes = $this->loadProperty($io->id, 'languageNotes', $culture);
        $languageOfDescription = $this->loadProperty($io->id, 'languageOfDescription', $culture);

        $parentTitle = null;
        $parentSlug = null;
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
                $parentSlug = $parent->slug;
            }
        }

        // RiC-O JSON-LD preview from the engine (guarded). Absent engine ->
        // null, and the view simply omits the panel.
        $ricJsonLd = $this->serializeRic($io->id);

        // In-editor SHACL / RiC-O conformance (#1425 A3). Runs on every GET,
        // which is also the page a save redirects to - so it doubles as the
        // post-save conformance report. Non-blocking: violations are shown, the
        // record still saves. Null when the engine/validator is unavailable.
        $ricValidation = $this->validateRic($ricJsonLd);

        return view('ric-manage::edit', array_merge(
            [
                'io' => $io,
                'events' => $events,
                'creators' => $creators,
                'notes' => $notes->whereNotIn('type_id', [220, 174])->values(),
                'subjects' => $accessPoints['subjects'],
                'places' => $accessPoints['places'],
                'genres' => $accessPoints['genres'],
                'nameAccessPoints' => $nameAccessPoints,
                'publicationStatusId' => $publicationStatusId,
                'materialLanguages' => $materialLanguages,
                'materialScripts' => $materialScripts,
                'languageNotes' => $languageNotes,
                'languageOfDescription' => $languageOfDescription,
                'parentTitle' => $parentTitle,
                'parentSlug' => $parentSlug,
                'ricJsonLd' => $ricJsonLd,
                'ricValidation' => $ricValidation,
            ],
            $dropdowns
        ));
    }

    /**
     * RiC-O conformance for the serialized entity (#1425 A3): SHACL shapes +
     * mandatory-field + referential-integrity checks via the engine's
     * ShaclValidationService. Returns ['valid'=>bool,'errors'=>[],'warnings'=>[]]
     * or null when there is nothing to validate / the validator is absent. The
     * RiC record type maps to the RiC-O 'Record' shape.
     */
    private function validateRic(?array $jsonLd): ?array
    {
        if (! is_array($jsonLd) || empty($jsonLd)) {
            return null;
        }
        if (! class_exists(\AhgRic\Services\ShaclValidationService::class)) {
            return null;
        }
        try {
            $type = $jsonLd['rico:type'] ?? 'Record';

            return app(\AhgRic\Services\ShaclValidationService::class)->validateBeforeSave($jsonLd, $type);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * RiC-O JSON-LD for the record, or null when the engine is unavailable.
     * The plugin must render its form on a bare install where ahg/ric might not
     * be booted, so every call into it is guarded.
     */
    private function serializeRic(int $ioId): ?array
    {
        if (! class_exists(\AhgRic\Services\RicSerializationService::class)) {
            return null;
        }
        try {
            $out = app(\AhgRic\Services\RicSerializationService::class)->serializeRecord($ioId);

            return is_array($out) && ! isset($out['error']) ? $out : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Best-effort triplestore sync after a save; never throws. */
    private function syncRic(int $ioId): void
    {
        if (! class_exists(\AhgRic\Services\FusekiSyncService::class)) {
            return;
        }
        try {
            $svc = app(\AhgRic\Services\FusekiSyncService::class);
            if (method_exists($svc, 'syncRecord')) {
                $svc->syncRecord($ioId);
            }
        } catch (\Throwable $e) {
            // no-op
        }
    }

    private function getFormDropdowns(string $culture): array
    {
        $termList = function (int $taxonomyId) use ($culture) {
            return DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', $taxonomyId)
                ->where('term_i18n.culture', $culture)
                ->orderBy('term_i18n.name')
                ->select('term.id', 'term_i18n.name')
                ->get();
        };

        $levels = $termList(34);
        $descriptionStatuses = $termList(44);
        $descriptionDetails = $termList(43);
        // Taxonomy 70 - the real description-standard taxonomy. NOT 52 (the
        // stale value the DACS clone still carries; #1425 risk note).
        $displayStandards = $termList(70);
        $eventTypes = $termList(40);

        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        return compact('levels', 'repositories', 'descriptionStatuses', 'descriptionDetails', 'displayStandards', 'eventTypes');
    }

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
        $existing = DB::table('property')->where('object_id', $objectId)->where('name', $name)->first();
        if ($existing) {
            DB::table('property_i18n')->where('id', $existing->id)->where('culture', $culture)->update(['value' => $value]);
        } elseif ($value !== null && $value !== '') {
            $propId = DB::table('property')->insertGetId(['object_id' => $objectId, 'name' => $name, 'source_culture' => $culture]);
            DB::table('property_i18n')->insert(['id' => $propId, 'culture' => $culture, 'value' => $value]);
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
            $decoded = @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($decoded)) {
                return collect($decoded);
            }
        }

        return collect();
    }

    private function saveSerializedProperty(int $objectId, string $name, array $values, string $culture): void
    {
        $serialized = serialize(array_values(array_filter($values)));
        $existing = DB::table('property')->where('object_id', $objectId)->where('name', $name)->first();
        if ($existing) {
            DB::table('property_i18n')->where('id', $existing->id)->where('culture', $culture)->update(['value' => $serialized]);
        } elseif (! empty($values)) {
            $propId = DB::table('property')->insertGetId(['object_id' => $objectId, 'name' => $name, 'source_culture' => $culture]);
            DB::table('property_i18n')->insert(['id' => $propId, 'culture' => $culture, 'value' => $serialized]);
        }
    }
}
