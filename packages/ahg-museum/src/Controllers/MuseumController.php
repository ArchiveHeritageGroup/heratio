<?php

namespace AhgMuseum\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgMuseum\Services\MuseumService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MuseumController extends Controller
{
    protected MuseumService $service;

    public function __construct()
    {
        $this->service = new MuseumService(app()->getLocale());
    }

    /**
     * Browse museum objects with filtering and pagination.
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ];

        $workType = $request->get('work_type');
        if ($workType) {
            $params['filters']['work_type'] = $workType;
        }

        $classification = $request->get('classification');
        if ($classification) {
            $params['filters']['classification'] = $classification;
        }

        $result = $this->service->browse($params);
        $pager = new SimplePager($result);

        return view('ahg-museum::museum.browse', [
            'pager' => $pager,
            'workTypes' => $result['workTypes'] ?? [],
            'classifications' => $result['classifications'] ?? [],
            'selectedWorkType' => $workType,
            'selectedClassification' => $classification,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
                'workType' => 'Work type',
                'creator' => 'Creator',
            ],
        ]);
    }

    /**
     * Show a museum object.
     */
    public function show(string $slug)
    {
        $culture = app()->getLocale();
        $museum = $this->service->getBySlug($slug);

        if (!$museum) {
            abort(404);
        }

        // Digital objects
        $digitalObjects = DigitalObjectService::getForObject($museum->id);

        // Repository
        $repository = null;
        if ($museum->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $museum->repository_id)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        // Level of description name
        $levelName = null;
        if ($museum->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $museum->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $museum->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $museum->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Publication status
        $publicationStatus = null;
        $publicationStatusId = null;
        $statusRow = DB::table('status')
            ->where('object_id', $museum->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Parent breadcrumb chain
        $breadcrumbs = [];
        $parentId = $museum->parent_id;
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

        return view('ahg-museum::museum.show', [
            'museum' => $museum,
            'digitalObjects' => $digitalObjects,
            'repository' => $repository,
            'levelName' => $levelName,
            'subjects' => $subjects,
            'places' => $places,
            'publicationStatus' => $publicationStatus,
            'publicationStatusId' => $publicationStatusId,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $culture = app()->getLocale();
        $formChoices = $this->service->getFormChoices($culture);

        return view('ahg-museum::museum.edit', array_merge(
            ['museum' => null, 'isNew' => true],
            $formChoices
        ));
    }

    /**
     * Store a new museum object.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'work_type' => 'nullable|string|max:50',
            'identifier' => 'nullable|string|max:1024',
            'creation_date_earliest' => 'nullable|date',
            'creation_date_latest' => 'nullable|date',
            'condition_date' => 'nullable|date',
            'treatment_date' => 'nullable|date',
            'cataloging_date' => 'nullable|date',
        ]);

        $slug = $this->service->create($request->all());

        return redirect()
            ->route('museum.show', $slug)
            ->with('success', 'Museum object created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();
        $museum = $this->service->getBySlug($slug);

        if (!$museum) {
            abort(404);
        }

        $formChoices = $this->service->getFormChoices($culture);

        return view('ahg-museum::museum.edit', array_merge(
            ['museum' => $museum, 'isNew' => false],
            $formChoices
        ));
    }

    /**
     * Update an existing museum object.
     */
    public function update(Request $request, string $slug)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'work_type' => 'nullable|string|max:50',
            'identifier' => 'nullable|string|max:1024',
            'creation_date_earliest' => 'nullable|date',
            'creation_date_latest' => 'nullable|date',
            'condition_date' => 'nullable|date',
            'treatment_date' => 'nullable|date',
            'cataloging_date' => 'nullable|date',
        ]);

        $this->service->update($slug, $request->all());

        return redirect()
            ->route('museum.show', $slug)
            ->with('success', 'Museum object updated successfully.');
    }

    /**
     * Delete a museum object.
     */
    public function destroy(string $slug)
    {
        $this->service->delete($slug);

        return redirect()
            ->route('museum.browse')
            ->with('success', 'Museum object deleted successfully.');
    }
}
