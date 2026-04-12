<?php

/**
 * RightsAdminController - Controller for Heratio
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



namespace AhgExtendedRights\Controllers;

use AhgExtendedRights\Services\ExtendedRightsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RightsAdminController
 *
 * Admin pages for managing rights, embargoes, orphan works, TK labels.
 * Migrated from AtoM ahgRightsPlugin rightsAdminActions.
 */
class RightsAdminController extends Controller
{
    protected ExtendedRightsService $service;

    public function __construct(ExtendedRightsService $service)
    {
        $this->service = $service;
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index()
    {
        $stats = $this->service->getStatistics();
        $expiringEmbargoes = $this->service->getExpiringEmbargoes(30);
        $reviewDue = $this->service->getEmbargoesForReview();
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.index', compact('stats', 'expiringEmbargoes', 'reviewDue', 'formOptions'));
    }

    // =========================================================================
    // EMBARGOES
    // =========================================================================

    public function embargoes(Request $request)
    {
        $status = $request->input('status', 'active');
        $embargoes = $this->service->getActiveEmbargoes($status);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.embargoes', compact('embargoes', 'status', 'formOptions'));
    }

    public function embargoEdit(Request $request, ?int $id = null)
    {
        $embargo = null;
        $embargoLog = collect();

        if ($id) {
            $embargo = $this->service->getEmbargoById($id);
            if (!$embargo) {
                abort(404);
            }
            $embargoLog = $this->service->getEmbargoLog($id);
        }

        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.embargo-edit', compact('embargo', 'embargoLog', 'formOptions'));
    }

    public function embargoStore(Request $request, ?int $id = null)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'embargo_type' => 'required|string',
            'reason' => 'required|string',
            'start_date' => 'required|date',
        ]);

        $data = $request->only([
            'object_id', 'embargo_type', 'reason', 'start_date', 'end_date',
            'auto_release', 'review_date', 'review_interval_months',
            'notify_before_days', 'notify_emails', 'reason_note', 'internal_note',
        ]);
        $data['auto_release'] = $request->has('auto_release') ? 1 : 0;

        if ($id) {
            $this->service->updateEmbargo($id, $data);
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo updated successfully.');
        } else {
            $this->service->createEmbargo($data);
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo created successfully.');
        }
    }

    public function embargoLift(Request $request, int $id)
    {
        $reason = $request->input('lift_reason');
        if ($this->service->liftEmbargo($id, $reason)) {
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo lifted successfully.');
        }
        return redirect()->route('ext-rights-admin.embargoes')->with('error', 'Failed to lift embargo.');
    }

    public function embargoExtend(Request $request, int $id)
    {
        $request->validate([
            'new_end_date' => 'required|date|after:today',
        ]);

        $reason = $request->input('extend_reason');
        if ($this->service->extendEmbargo($id, $request->input('new_end_date'), $reason)) {
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo extended successfully.');
        }
        return redirect()->route('ext-rights-admin.embargoes')->with('error', 'Failed to extend embargo.');
    }

    public function processExpired()
    {
        $count = $this->service->processExpiredEmbargoes();
        return redirect()->route('ext-rights-admin.embargoes')->with('success', "Processed {$count} expired embargoes.");
    }

    // =========================================================================
    // ORPHAN WORKS
    // =========================================================================

    public function orphanWorks(Request $request)
    {
        $status = $request->input('status', 'all');
        $orphanWorks = $this->service->getOrphanWorks($status);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.orphan-works', compact('orphanWorks', 'status', 'formOptions'));
    }

    public function orphanWorkEdit(Request $request, ?int $id = null)
    {
        $orphanWork = null;
        $searchSteps = collect();

        if ($id) {
            $orphanWork = $this->service->getOrphanWorkById($id);
            if (!$orphanWork) {
                abort(404);
            }
            $searchSteps = $this->service->getOrphanWorkSearchSteps($id);
        }

        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.orphan-work-edit', compact('orphanWork', 'searchSteps', 'formOptions'));
    }

    public function orphanWorkStore(Request $request, ?int $id = null)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'work_type' => 'required|string',
        ]);

        $data = $request->only([
            'object_id', 'work_type', 'search_jurisdiction',
            'intended_use', 'proposed_fee', 'notes',
        ]);

        if ($id) {
            $this->service->updateOrphanWork($id, $data);
            return redirect()->route('ext-rights-admin.orphan-work-edit', $id)->with('success', 'Orphan work record updated.');
        } else {
            $newId = $this->service->createOrphanWork($data);
            return redirect()->route('ext-rights-admin.orphan-work-edit', $newId)->with('success', 'Orphan work record created.');
        }
    }

    public function addSearchStep(Request $request, int $orphanWorkId)
    {
        $request->validate([
            'source_type' => 'required|string',
            'source_name' => 'required|string',
            'search_date' => 'required|date',
        ]);

        $data = $request->only([
            'source_type', 'source_name', 'source_url', 'search_date',
            'search_terms', 'results_found', 'results_description',
        ]);
        $data['results_found'] = $request->has('results_found') ? 1 : 0;

        $this->service->addOrphanWorkSearchStep($orphanWorkId, $data);

        return redirect()->route('ext-rights-admin.orphan-work-edit', $orphanWorkId)->with('success', 'Search step added.');
    }

    public function completeOrphanSearch(Request $request, int $id)
    {
        $found = $request->boolean('rights_holder_found');
        $this->service->completeOrphanWorkSearch($id, $found);

        return redirect()->route('ext-rights-admin.orphan-works')->with('success', 'Search marked as complete.');
    }

    // =========================================================================
    // TK LABELS
    // =========================================================================

    public function tkLabels()
    {
        $tkLabels = $this->service->getTkLabels();
        $assignments = $this->service->getTkLabelAssignments();

        return view('ahg-extended-rights::admin.tk-labels', compact('tkLabels', 'assignments'));
    }

    public function assignTkLabel(Request $request)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'tk_label_id' => 'required|integer',
        ]);

        $data = $request->only(['community_name', 'community_contact', 'custom_text']);

        $this->service->assignTkLabel(
            (int) $request->input('object_id'),
            (int) $request->input('tk_label_id'),
            $data
        );

        return redirect()->route('ext-rights-admin.tk-labels')->with('success', 'TK Label assigned successfully.');
    }

    public function removeTkLabel(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $labelId = (int) $request->input('label_id');

        if ($this->service->removeTkLabel($objectId, $labelId)) {
            return redirect()->route('ext-rights-admin.tk-labels')->with('success', 'TK Label removed.');
        }
        return redirect()->route('ext-rights-admin.tk-labels')->with('error', 'Failed to remove TK Label.');
    }

    // =========================================================================
    // RIGHTS STATEMENTS & CC LICENSES
    // =========================================================================

    public function statements()
    {
        $rightsStatements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCcLicenses();

        return view('ahg-extended-rights::admin.statements', compact('rightsStatements', 'ccLicenses'));
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function report(Request $request)
    {
        $type = $request->input('type', 'summary');
        $data = $this->service->getReportData($type);

        if ($request->input('export') === 'csv' && $type !== 'summary') {
            $this->service->exportReportCsv($type, $data);
            return null;
        }

        return view('ahg-extended-rights::admin.report', compact('type', 'data'));
    }

    // =========================================================================
    // BATCH RIGHTS ASSIGNMENT (cloned from AtoM extendedRights/batchAction)
    // =========================================================================

    public function batch()
    {
        $statements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCcLicenses();
        $tkLabels = $this->service->getTkLabels();
        $repositories = DB::table('repository')->orderBy('id')->limit(500)->get();
        $recentBatches = collect();

        return view('ahg-extended-rights::admin.batch', compact('statements', 'ccLicenses', 'tkLabels', 'repositories', 'recentBatches'));
    }

    public function batchStore(Request $request)
    {
        $action = $request->input('batch_action', 'assign');
        $objectIds = array_filter(array_map('intval', (array) $request->input('object_ids', [])));

        if (empty($objectIds)) {
            return redirect()->route('ext-rights-admin.batch')->with('error', 'Please select at least one object.');
        }

        $count = 0;
        foreach ($objectIds as $objectId) {
            switch ($action) {
                case 'assign':
                    $rsId = (int) $request->input('rights_statement_id');
                    $ccId = (int) $request->input('creative_commons_id');
                    if ($rsId) {
                        $this->service->saveRightsRecord([
                            'object_id' => $objectId,
                            'rights_type' => 'rights_statement',
                            'rights_value' => (string) $rsId,
                        ]);
                    }
                    if ($ccId) {
                        $this->service->saveRightsRecord([
                            'object_id' => $objectId,
                            'rights_type' => 'cc_license',
                            'rights_value' => (string) $ccId,
                        ]);
                    }
                    $count++;
                    break;
                case 'embargo':
                    $this->service->createEmbargo([
                        'object_id' => $objectId,
                        'embargo_type' => $request->input('embargo_type', 'full'),
                        'start_date' => date('Y-m-d'),
                        'end_date' => $request->input('embargo_end_date'),
                    ]);
                    $count++;
                    break;
            }
        }

        return redirect()->route('ext-rights-admin.batch')->with('success', "Processed {$count} object(s).");
    }

    // =========================================================================
    // BROWSE RIGHTS (cloned from AtoM extendedRights/browseAction)
    // =========================================================================

    public function browse(Request $request)
    {
        $type = $request->input('type');
        $q = $request->input('q');
        $repositoryId = $request->input('repository');

        $query = DB::table('rights_record as r')
            ->leftJoin('information_object as o', 'r.object_id', '=', 'o.id')
            ->leftJoin('information_object_i18n as oi', function ($j) {
                $j->on('o.id', '=', 'oi.id')->where('oi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'o.id', '=', 's.object_id')
            ->select('r.*', 'oi.title', 's.slug', 'o.repository_id');

        if ($type) {
            $query->where('r.rights_type', $type);
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('oi.title', 'like', "%{$q}%")
                  ->orWhere('r.object_id', $q);
            });
        }
        if ($repositoryId) {
            $query->where('o.repository_id', $repositoryId);
        }

        $rights = $query->orderBy('r.created_at', 'desc')->limit(200)->get();
        $repositories = DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->select('r.id', 'ai.authorized_form_of_name as name')
            ->orderBy('name')
            ->get();

        return view('ahg-extended-rights::admin.browse', compact('rights', 'repositories'));
    }

    // =========================================================================
    // EXPORT RIGHTS (cloned from AtoM extendedRights/exportAction)
    // =========================================================================

    public function export()
    {
        $stats = $this->service->getStatistics();
        $repositories = DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->select('r.id', 'ai.authorized_form_of_name as name')
            ->orderBy('name')
            ->get();

        return view('ahg-extended-rights::admin.export', compact('stats', 'repositories'));
    }

    public function exportCsv(Request $request)
    {
        $repositoryId = $request->input('repository');
        $type = $request->input('type');

        $query = DB::table('rights_record as r')
            ->leftJoin('information_object as o', 'r.object_id', '=', 'o.id')
            ->leftJoin('information_object_i18n as oi', function ($j) {
                $j->on('o.id', '=', 'oi.id')->where('oi.culture', '=', 'en');
            })
            ->select('r.id', 'r.object_id', 'oi.title', 'r.rights_type', 'r.rights_value', 'r.rights_date');

        if ($repositoryId) {
            $query->where('o.repository_id', $repositoryId);
        }
        if ($type) {
            $query->where('r.rights_type', $type);
        }

        $rows = $query->orderBy('r.id')->get();

        $filename = 'rights_export_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Rights ID', 'Object ID', 'Title', 'Type', 'Value', 'Date']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->id, $row->object_id, $row->title, $row->rights_type, $row->rights_value, $row->rights_date]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function exportJsonld(Request $request)
    {
        $repositoryId = $request->input('repository');

        $query = DB::table('rights_record as r')
            ->leftJoin('information_object as o', 'r.object_id', '=', 'o.id')
            ->select('r.*', 'o.repository_id');

        if ($repositoryId) {
            $query->where('o.repository_id', $repositoryId);
        }

        $rows = $query->limit(5000)->get();

        $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => 'Heratio Rights Export',
            'dateExported' => date('c'),
            'hasPart' => $rows->map(fn($r) => [
                '@type' => 'CreativeWork',
                'identifier' => $r->object_id,
                'license' => $r->rights_value,
                'rightsType' => $r->rights_type,
            ]),
        ];

        return response()->json($jsonld, 200, [
            'Content-Disposition' => 'attachment; filename="rights_export_' . date('Y-m-d') . '.jsonld"',
        ], JSON_PRETTY_PRINT);
    }

    // =========================================================================
    // EXPIRING EMBARGOES (the dashboard's "Expiring Soon" page)
    // =========================================================================

    public function expiringEmbargoes(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $expiring = $this->service->getExpiringEmbargoes($days);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.expiring-embargoes', compact('expiring', 'days', 'formOptions'));
    }
}
