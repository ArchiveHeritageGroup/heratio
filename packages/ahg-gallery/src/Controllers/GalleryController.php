<?php

namespace AhgGallery\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgGallery\Services\GalleryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    protected GalleryService $service;

    public function __construct(GalleryService $service)
    {
        $this->service = $service;
    }

    /**
     * Browse gallery artworks.
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ];

        $repositoryId = $request->get('repository');
        if ($repositoryId) {
            $params['filters']['repository_id'] = $repositoryId;
        }

        $result = $this->service->browse($params, $culture);

        $pager = new SimplePager($result);

        // Get list of repositories for filter dropdown
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        return view('ahg-gallery::gallery.browse', [
            'pager' => $pager,
            'repositoryNames' => $result['repositoryNames'] ?? [],
            'repositories' => $repositories,
            'selectedRepository' => $repositoryId,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
                'artist' => 'Artist',
            ],
        ]);
    }

    /**
     * Show a single gallery artwork.
     */
    public function show(string $slug)
    {
        $culture = app()->getLocale();

        $artwork = $this->service->getBySlug($slug, $culture);

        if (!$artwork) {
            abort(404);
        }

        // Repository
        $repository = null;
        if ($artwork->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $artwork->repository_id)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        // Digital objects
        $digitalObjects = DigitalObjectService::getForObject($artwork->id);

        // Events (dates)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $artwork->id)
            ->where('event_i18n.culture', $culture)
            ->select(
                'event.id',
                'event.type_id',
                'event.actor_id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name'
            )
            ->get();

        // Creators (events where type_id = 111 = creation)
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $artwork->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select(
                'event.actor_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.history',
                'slug.slug'
            )
            ->distinct()
            ->get();

        // Notes
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $artwork->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        $noteTypeIds = $notes->pluck('type_id')->filter()->unique()->values()->toArray();
        $noteTypeNames = [];
        if (!empty($noteTypeIds)) {
            $noteTypeNames = DB::table('term_i18n')
                ->whereIn('id', $noteTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $artwork->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $artwork->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Genre access points (taxonomy_id = 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $artwork->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Publication status
        $publicationStatus = null;
        $publicationStatusId = null;
        $statusRow = DB::table('status')
            ->where('object_id', $artwork->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Physical storage
        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.object_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->where('relation.subject_id', $artwork->id)
            ->where('relation.type_id', 151)
            ->where('physical_object_i18n.culture', $culture)
            ->select('physical_object.id', 'physical_object_i18n.name', 'physical_object_i18n.location', 'physical_object.type_id')
            ->get();

        // Level name
        $levelName = null;
        if ($artwork->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $artwork->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Parent breadcrumbs
        $breadcrumbs = [];
        $parentId = $artwork->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();
            if (!$parent) {
                break;
            }
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        // Related gallery artist record (if creator_identity matches)
        $galleryArtist = null;
        if ($artwork->creator_identity) {
            $galleryArtist = DB::table('gallery_artist')
                ->where('display_name', $artwork->creator_identity)
                ->first();
        }

        return view('ahg-gallery::gallery.show', [
            'artwork' => $artwork,
            'levelName' => $levelName,
            'repository' => $repository,
            'digitalObjects' => $digitalObjects,
            'events' => $events,
            'creators' => $creators,
            'notes' => $notes,
            'noteTypeNames' => $noteTypeNames,
            'subjects' => $subjects,
            'places' => $places,
            'genres' => $genres,
            'publicationStatus' => $publicationStatus,
            'publicationStatusId' => $publicationStatusId,
            'physicalObjects' => $physicalObjects,
            'breadcrumbs' => $breadcrumbs,
            'galleryArtist' => $galleryArtist,
        ]);
    }

    /**
     * Show create form for a gallery artwork.
     */
    public function create(Request $request)
    {
        $culture = app()->getLocale();
        $choices = $this->service->getFormChoices($culture);

        return view('ahg-gallery::gallery.edit', array_merge([
            'artwork' => null,
            'isNew' => true,
        ], $choices));
    }

    /**
     * Store a new gallery artwork.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:65535',
            'work_type' => 'nullable|string|max:255',
            'classification' => 'nullable|string|max:255',
            'identifier' => 'nullable|string|max:255',
            'creator_identity' => 'nullable|string|max:1024',
            'creator_role' => 'nullable|string|max:255',
            'creation_date_display' => 'nullable|string|max:255',
            'creation_date_earliest' => 'nullable|string|max:255',
            'creation_date_latest' => 'nullable|string|max:255',
            'creation_place' => 'nullable|string|max:1024',
            'style' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:255',
            'movement' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'measurements' => 'nullable|string|max:1024',
            'dimensions' => 'nullable|string|max:1024',
            'materials' => 'nullable|string|max:1024',
            'techniques' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string|max:65535',
            'inscription' => 'nullable|string|max:65535',
            'mark_description' => 'nullable|string|max:65535',
            'condition_term' => 'nullable|string|max:255',
            'condition_description' => 'nullable|string|max:65535',
            'provenance' => 'nullable|string|max:65535',
            'current_location' => 'nullable|string|max:1024',
            'rights_type' => 'nullable|string|max:255',
            'rights_holder' => 'nullable|string|max:1024',
            'cataloger_name' => 'nullable|string|max:255',
            'cataloging_date' => 'nullable|string|max:255',
            'repository_id' => 'nullable|integer',
        ]);

        $slug = $this->service->create($request->all(), app()->getLocale());

        return redirect()
            ->route('gallery.show', $slug)
            ->with('success', 'Gallery artwork created successfully.');
    }

    /**
     * Show edit form for a gallery artwork.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();

        $artwork = $this->service->getBySlug($slug, $culture);

        if (!$artwork) {
            abort(404);
        }

        $choices = $this->service->getFormChoices($culture);

        return view('ahg-gallery::gallery.edit', array_merge([
            'artwork' => $artwork,
            'isNew' => false,
        ], $choices));
    }

    /**
     * Update a gallery artwork.
     */
    public function update(Request $request, string $slug)
    {
        $request->validate([
            'title' => 'required|string|max:65535',
            'work_type' => 'nullable|string|max:255',
            'classification' => 'nullable|string|max:255',
            'identifier' => 'nullable|string|max:255',
            'creator_identity' => 'nullable|string|max:1024',
            'creator_role' => 'nullable|string|max:255',
            'creation_date_display' => 'nullable|string|max:255',
            'creation_date_earliest' => 'nullable|string|max:255',
            'creation_date_latest' => 'nullable|string|max:255',
            'creation_place' => 'nullable|string|max:1024',
            'style' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:255',
            'movement' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'measurements' => 'nullable|string|max:1024',
            'dimensions' => 'nullable|string|max:1024',
            'materials' => 'nullable|string|max:1024',
            'techniques' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string|max:65535',
            'inscription' => 'nullable|string|max:65535',
            'mark_description' => 'nullable|string|max:65535',
            'condition_term' => 'nullable|string|max:255',
            'condition_description' => 'nullable|string|max:65535',
            'provenance' => 'nullable|string|max:65535',
            'current_location' => 'nullable|string|max:1024',
            'rights_type' => 'nullable|string|max:255',
            'rights_holder' => 'nullable|string|max:1024',
            'cataloger_name' => 'nullable|string|max:255',
            'cataloging_date' => 'nullable|string|max:255',
            'repository_id' => 'nullable|integer',
        ]);

        $this->service->update($slug, $request->all(), app()->getLocale());

        return redirect()
            ->route('gallery.show', $slug)
            ->with('success', 'Gallery artwork updated successfully.');
    }

    /**
     * Delete a gallery artwork.
     */
    public function destroy(string $slug)
    {
        $this->service->delete($slug);

        return redirect()
            ->route('gallery.browse')
            ->with('success', 'Gallery artwork deleted successfully.');
    }

    /**
     * Browse gallery artists.
     */
    public function artists(Request $request)
    {
        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ];

        $result = $this->service->getArtists($params);

        $pager = new SimplePager($result);

        return view('ahg-gallery::gallery.artists', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'nationality' => 'Nationality',
            ],
        ]);
    }

    /**
     * Show a single gallery artist.
     */
    public function showArtist(int $id)
    {
        $artist = $this->service->getArtist($id);

        if (!$artist) {
            abort(404);
        }

        return view('ahg-gallery::gallery.artist-show', [
            'artist' => $artist,
        ]);
    }

    /**
     * Show create form for a gallery artist.
     */
    public function createArtist()
    {
        $culture = app()->getLocale();
        $choices = $this->service->getFormChoices($culture);

        return view('ahg-gallery::gallery.artist-create', [
            'artistTypes' => $choices['artistTypes'],
        ]);
    }

    /**
     * Store a new gallery artist.
     */
    public function storeArtist(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:1024',
            'sort_name' => 'nullable|string|max:1024',
            'birth_date' => 'nullable|string|max:255',
            'birth_place' => 'nullable|string|max:1024',
            'death_date' => 'nullable|string|max:255',
            'death_place' => 'nullable|string|max:1024',
            'nationality' => 'nullable|string|max:255',
            'artist_type' => 'nullable|string|max:255',
            'medium_specialty' => 'nullable|string|max:1024',
            'movement_style' => 'nullable|string|max:1024',
            'active_period' => 'nullable|string|max:255',
            'represented' => 'nullable|string|max:1024',
            'biography' => 'nullable|string|max:65535',
            'artist_statement' => 'nullable|string|max:65535',
            'cv' => 'nullable|string|max:65535',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:1024',
            'studio_address' => 'nullable|string|max:1024',
            'instagram' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:65535',
            'is_active' => 'nullable|boolean',
        ]);

        $id = $this->service->createArtist($request->all());

        return redirect()
            ->route('gallery.artists.show', $id)
            ->with('success', 'Gallery artist created successfully.');
    }
}
