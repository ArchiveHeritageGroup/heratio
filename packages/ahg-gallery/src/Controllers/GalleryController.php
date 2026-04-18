<?php

/**
 * GalleryController - Controller for Heratio
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



namespace AhgGallery\Controllers;

use AhgCore\Constants\TermId;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgGallery\Services\GalleryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Creators (creation events)
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $artwork->id)
            ->where('event.type_id', TermId::EVENT_TYPE_CREATION)
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
            ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Physical storage
        // AtoM: subject=physical_object, object=artwork(IO), type=RELATION_HAS_PHYSICAL_OBJECT.
        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.subject_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->where('relation.object_id', $artwork->id)
            ->where('relation.type_id', TermId::RELATION_HAS_PHYSICAL_OBJECT)
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
        $editExtras = $this->service->getEditExtras(null, $culture);

        return view('ahg-gallery::gallery.edit', array_merge([
            'artwork' => null,
            'isNew' => true,
        ], $choices, $editExtras));
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
        $editExtras = $this->service->getEditExtras($artwork->id ?? null, $culture);

        return view('ahg-gallery::gallery.edit', array_merge([
            'artwork' => $artwork,
            'isNew' => false,
        ], $choices, $editExtras));
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

    /** Gallery dashboard. */
    public function dashboard()
    {
        $totalItems = Schema::hasTable('gallery_artwork') ? DB::table('gallery_artwork')->count() : 0;
        $itemsWithMedia = Schema::hasTable('gallery_artwork') ? DB::table('gallery_artwork')->whereNotNull('digital_object_id')->count() : 0;
        $totalArtists = Schema::hasTable('gallery_artist') ? DB::table('gallery_artist')->count() : 0;
        $activeLoans = Schema::hasTable('gallery_loan') ? DB::table('gallery_loan')->where('status', 'active')->count() : 0;
        $recentItems = Schema::hasTable('gallery_artwork') ? DB::table('gallery_artwork')->orderBy('created_at', 'desc')->limit(10)->get() : collect();
        return view('ahg-gallery::gallery.dashboard', compact('totalItems', 'itemsWithMedia', 'totalArtists', 'activeLoans', 'recentItems'));
    }

    /** Gallery index page. */
    public function galleryIndex() { return view('ahg-gallery::gallery.index'); }

    /** Loans list. */
    public function loans()
    {
        $loans = Schema::hasTable('gallery_loan') ? DB::table('gallery_loan')->orderBy('created_at', 'desc')->get() : collect();
        return view('ahg-gallery::gallery.loans', compact('loans'));
    }

    public function showLoan(int $id)
    {
        $loan = DB::table('gallery_loan')->where('id', $id)->first();
        if (!$loan) abort(404);
        return view('ahg-gallery::gallery.view-loan', compact('loan'));
    }

    public function createLoan() { return view('ahg-gallery::gallery.create-loan'); }
    public function storeLoan(Request $request)
    {
        $request->validate(['title' => 'required|string|max:1024']);
        if (Schema::hasTable('gallery_loan')) {
            DB::table('gallery_loan')->insert(array_merge($request->only(['title','loan_type','borrower_name','start_date','end_date','insurance_value','loan_fee','conditions','notes']), ['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        }
        return redirect()->route('gallery.loans')->with('success', 'Loan created.');
    }

    /** Valuations list. */
    public function valuations()
    {
        $valuations = Schema::hasTable('gallery_valuation') ? DB::table('gallery_valuation')->orderBy('created_at', 'desc')->get() : collect();
        return view('ahg-gallery::gallery.valuations', compact('valuations'));
    }

    public function showValuation(int $id)
    {
        $valuation = DB::table('gallery_valuation')->where('id', $id)->first();
        if (!$valuation) abort(404);
        return view('ahg-gallery::gallery.valuations', ['valuations' => collect([$valuation])]);
    }

    public function createValuation() { return view('ahg-gallery::gallery.create-valuation'); }
    public function storeValuation(Request $request)
    {
        $request->validate(['value' => 'required|numeric']);
        if (Schema::hasTable('gallery_valuation')) {
            DB::table('gallery_valuation')->insert(array_merge($request->only(['valuation_type','value','valuation_date','appraiser','notes']), ['created_at' => now(), 'updated_at' => now()]));
        }
        return redirect()->route('gallery.valuations')->with('success', 'Valuation created.');
    }

    /** Venues list. */
    public function venues()
    {
        $venues = Schema::hasTable('gallery_venue') ? DB::table('gallery_venue')->orderBy('name')->get() : collect();
        return view('ahg-gallery::gallery.venues', compact('venues'));
    }

    public function showVenue(int $id)
    {
        $venue = DB::table('gallery_venue')->where('id', $id)->first();
        if (!$venue) abort(404);
        return view('ahg-gallery::gallery.view-venue', compact('venue'));
    }

    public function createVenue() { return view('ahg-gallery::gallery.create-venue'); }
    public function storeVenue(Request $request)
    {
        $request->validate(['name' => 'required|string|max:1024']);
        if (Schema::hasTable('gallery_venue')) {
            DB::table('gallery_venue')->insert(array_merge($request->only(['name','venue_type','address','city','country','contact_person','email','notes']), ['created_at' => now(), 'updated_at' => now()]));
        }
        return redirect()->route('gallery.venues')->with('success', 'Venue created.');
    }

    /** Facility Report. */
    public function facilityReport(int $id)
    {
        $report = Schema::hasTable('gallery_facility_report') ? DB::table('gallery_facility_report')->where('id', $id)->first() : null;
        if (!$report) abort(404);
        return view('ahg-gallery::gallery.facility-report', compact('report'));
    }

    /** Gallery Reports */
    public function reportsIndex()
    {
        $stats = [
            'exhibitions' => ['total' => 0, 'open' => 0, 'planning' => 0, 'upcoming' => 0],
            'artists' => ['total' => 0, 'represented' => 0, 'active' => 0],
            'loans' => ['total' => 0, 'active' => 0, 'incoming' => 0, 'outgoing' => 0, 'pending' => 0],
            'valuations' => ['total' => 0, 'current' => 0, 'totalValue' => 0, 'expiringSoon' => 0],
        ];
        try {
            if (Schema::hasTable('gallery_exhibition')) {
                $stats['exhibitions']['total'] = DB::table('gallery_exhibition')->count();
                $stats['exhibitions']['open'] = DB::table('gallery_exhibition')->where('status', 'open')->count();
                $stats['exhibitions']['planning'] = DB::table('gallery_exhibition')->where('status', 'planning')->count();
                $stats['exhibitions']['upcoming'] = DB::table('gallery_exhibition')
                    ->where('start_date', '>', now())->count();
            }
            if (Schema::hasTable('gallery_artist')) {
                $stats['artists']['total'] = DB::table('gallery_artist')->count();
                $stats['artists']['represented'] = DB::table('gallery_artist')->where('is_represented', 1)->count();
                $stats['artists']['active'] = DB::table('gallery_artist')->where('is_active', 1)->count();
            }
            if (Schema::hasTable('gallery_loan')) {
                $stats['loans']['total'] = DB::table('gallery_loan')->count();
                $stats['loans']['active'] = DB::table('gallery_loan')->where('status', 'active')->count();
                $stats['loans']['incoming'] = DB::table('gallery_loan')->where('direction', 'incoming')->count();
                $stats['loans']['outgoing'] = DB::table('gallery_loan')->where('direction', 'outgoing')->count();
                $stats['loans']['pending'] = DB::table('gallery_loan')->where('status', 'pending')->count();
            }
            if (Schema::hasTable('gallery_valuation')) {
                $stats['valuations']['total'] = DB::table('gallery_valuation')->count();
                $stats['valuations']['current'] = DB::table('gallery_valuation')->where('is_current', 1)->count();
                $stats['valuations']['totalValue'] = (float) DB::table('gallery_valuation')->where('is_current', 1)->sum('value');
                $stats['valuations']['expiringSoon'] = DB::table('gallery_valuation')
                    ->where('is_current', 1)
                    ->where('expiry_date', '<=', now()->addDays(90))
                    ->where('expiry_date', '>', now())
                    ->count();
            }
        } catch (\Throwable $e) {
            // Tables may have different column names — keep defaults
        }
        return view('ahg-gallery::galleryReports.index', compact('stats'));
    }

    public function reportsExhibitions() { $items = Schema::hasTable('gallery_exhibition') ? DB::table('gallery_exhibition')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-gallery::galleryReports.exhibitions', compact('items')); }
    public function reportsFacilityReports() { $items = Schema::hasTable('gallery_facility_report') ? DB::table('gallery_facility_report')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-gallery::galleryReports.facility-reports', compact('items')); }
    public function reportsLoans() { $items = Schema::hasTable('gallery_loan') ? DB::table('gallery_loan')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-gallery::galleryReports.loans', compact('items')); }
    public function reportsSpaces() { $items = Schema::hasTable('gallery_space') ? DB::table('gallery_space')->orderBy('name')->get() : collect(); return view('ahg-gallery::galleryReports.spaces', compact('items')); }
    public function reportsValuations() { $items = Schema::hasTable('gallery_valuation') ? DB::table('gallery_valuation')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-gallery::galleryReports.valuations', compact('items')); }

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
