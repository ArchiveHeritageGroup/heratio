<?php

declare(strict_types=1);

namespace AhgExtendedRights\Controllers;

use AhgExtendedRights\Services\ExtendedRightsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RightsController
 *
 * Object-level rights management: view, add, edit, delete rights records,
 * embargo management per object, TK label assignment, orphan work status.
 * Migrated from AtoM ahgRightsPlugin rightsActions.
 */
class RightsController extends Controller
{
    protected ExtendedRightsService $service;

    public function __construct(ExtendedRightsService $service)
    {
        $this->service = $service;
    }

    /**
     * View rights for an object
     */
    public function index(string $slug)
    {
        $resource = $this->resolveResource($slug);

        $rights = $this->service->getRightsForObject($resource->id);
        $embargo = $this->service->getEmbargo($resource->id);
        $tkLabels = $this->service->getTkLabelsForObject($resource->id);
        $orphanWork = $this->service->getOrphanWork($resource->id);
        $accessCheck = $this->service->checkAccess($resource->id, auth()->id());
        $canEdit = auth()->check() && (auth()->user()->is_admin ?? false);

        return view('ahg-extended-rights::rights.index', compact(
            'resource', 'rights', 'embargo', 'tkLabels', 'orphanWork', 'accessCheck', 'canEdit'
        ));
    }

    /**
     * Add new rights record
     */
    public function add(string $slug)
    {
        $resource = $this->resolveResource($slug);
        $formOptions = $this->service->getFormOptions();
        $isNew = true;
        $right = null;

        return view('ahg-extended-rights::rights.edit', compact('resource', 'formOptions', 'isNew', 'right'));
    }

    /**
     * Edit existing rights record
     */
    public function edit(string $slug, int $id)
    {
        $resource = $this->resolveResource($slug);
        $right = $this->service->getRightsRecord($id);

        if (!$right || $right->object_id !== $resource->id) {
            abort(404);
        }

        $formOptions = $this->service->getFormOptions();
        $isNew = false;

        return view('ahg-extended-rights::rights.edit', compact('resource', 'formOptions', 'isNew', 'right'));
    }

    /**
     * Store (create or update) a rights record
     */
    public function store(Request $request, string $slug, ?int $id = null)
    {
        $resource = $this->resolveResource($slug);

        $request->validate([
            'basis' => 'required|string',
        ]);

        $data = $request->only([
            'basis', 'basis_note', 'rights_statement_id', 'copyright_status',
            'copyright_jurisdiction', 'copyright_status_date', 'copyright_holder',
            'copyright_expiry_date', 'copyright_note', 'license_type', 'cc_license_id',
            'license_identifier', 'license_terms', 'license_url', 'license_note',
            'statute_jurisdiction', 'statute_citation', 'statute_determination_date',
            'statute_note', 'donor_name', 'policy_identifier', 'start_date', 'end_date',
            'rights_holder_name', 'rights_note',
        ]);
        $data['id'] = $id;
        $data['object_id'] = $resource->id;
        $data['end_date_open'] = $request->has('end_date_open') ? 1 : 0;

        // Process granted rights
        $grantedRights = [];
        $acts = $request->input('acts', []);
        $restrictions = $request->input('restrictions', []);
        $restrictionReasons = $request->input('restriction_reasons', []);

        if (is_array($acts)) {
            foreach ($acts as $i => $act) {
                if (!empty($act)) {
                    $grantedRights[] = [
                        'act' => $act,
                        'restriction' => $restrictions[$i] ?? 'allow',
                        'restriction_reason' => $restrictionReasons[$i] ?? null,
                    ];
                }
            }
        }
        $data['granted_rights'] = $grantedRights;

        $this->service->saveRightsRecord($data);

        return redirect()->route('ext-rights.index', $slug)->with('success', 'Rights record saved.');
    }

    /**
     * Delete rights record
     */
    public function delete(Request $request, string $slug, int $id)
    {
        $resource = $this->resolveResource($slug);
        $this->service->deleteRightsRecord($id);

        return redirect()->route('ext-rights.index', $slug)->with('success', 'Rights record deleted.');
    }

    /**
     * Edit embargo for object
     */
    public function editEmbargo(string $slug)
    {
        $resource = $this->resolveResource($slug);
        $embargo = $this->service->getEmbargo($resource->id);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::rights.edit-embargo', compact('resource', 'embargo', 'formOptions'));
    }

    /**
     * Store embargo for object
     */
    public function storeEmbargo(Request $request, string $slug)
    {
        $resource = $this->resolveResource($slug);

        $request->validate([
            'embargo_type' => 'required|string',
            'reason' => 'required|string',
            'start_date' => 'required|date',
        ]);

        $data = $request->only([
            'embargo_type', 'reason', 'start_date', 'end_date',
            'auto_release', 'reason_note', 'internal_note',
        ]);
        $data['object_id'] = $resource->id;
        $data['auto_release'] = $request->has('auto_release') ? 1 : 0;

        $embargo = $this->service->getEmbargo($resource->id);
        if ($embargo) {
            $this->service->updateEmbargo($embargo->id, $data);
        } else {
            $this->service->createEmbargo($data);
        }

        return redirect()->route('ext-rights.index', $slug)->with('success', 'Embargo saved.');
    }

    /**
     * Release embargo
     */
    public function releaseEmbargo(Request $request, string $slug, int $id)
    {
        $this->service->liftEmbargo($id);
        return redirect()->route('ext-rights.index', $slug)->with('success', 'Embargo released.');
    }

    /**
     * TK Labels management for object
     */
    public function tkLabels(Request $request, string $slug)
    {
        $resource = $this->resolveResource($slug);
        $availableLabels = $this->service->getTkLabels();
        $assignedLabels = $this->service->getTkLabelsForObject($resource->id);

        return view('ahg-extended-rights::rights.tk-labels', compact('resource', 'availableLabels', 'assignedLabels'));
    }

    public function assignTkLabel(Request $request, string $slug)
    {
        $resource = $this->resolveResource($slug);

        $request->validate([
            'tk_label_id' => 'required|integer',
        ]);

        $data = $request->only(['community_name', 'community_contact', 'provenance_statement', 'cultural_note']);

        $this->service->assignTkLabel(
            $resource->id,
            (int) $request->input('tk_label_id'),
            $data
        );

        return redirect()->route('ext-rights.tk-labels', $slug)->with('success', 'TK Label assigned.');
    }

    /**
     * Orphan work management for object
     */
    public function orphanWork(Request $request, string $slug)
    {
        $resource = $this->resolveResource($slug);
        $orphanWork = $this->service->getOrphanWork($resource->id);

        return view('ahg-extended-rights::rights.orphan-work', compact('resource', 'orphanWork'));
    }

    /**
     * API: Check access rights (JSON)
     */
    public function apiCheck(int $id)
    {
        $result = $this->service->checkAccess($id, auth()->id());
        return response()->json($result);
    }

    /**
     * API: Get embargo status (JSON)
     */
    public function apiEmbargo(int $id)
    {
        $embargo = $this->service->getEmbargo($id);
        return response()->json([
            'embargoed' => $embargo !== null,
            'embargo' => $embargo,
        ]);
    }

    /**
     * Resolve information object from slug
     */
    protected function resolveResource(string $slug): object
    {
        $culture = app()->getLocale();
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            abort(404);
        }

        $resource = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $objectId)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$resource) {
            abort(404);
        }

        return $resource;
    }
}
