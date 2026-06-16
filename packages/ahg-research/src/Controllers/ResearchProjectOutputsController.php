<?php

/**
 * ResearchProjectOutputsController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchProjectOutputsController - Project analysis, visualization and
 * research-output tools (project subsystem, issue #1269).
 *
 * Extracted alongside ResearchProjectsController. This sibling carries the
 * heavier per-project tooling: the analysis cluster (knowledgeGraph, assertions,
 * hypotheses, extractionJobs, snapshots, viewSnapshot, assertionBatchReview),
 * the visualization cluster (timelineBuilder, mapBuilder, networkGraph) and the
 * research-output cluster (roCrate, reproducibilityPack, mintDoi,
 * ethicsMilestones, complianceDashboard).
 *
 * The 20-method project subsystem was split across two controllers to keep each
 * under the size budget. Every method here is auth-gated and uses only the
 * shared trait helper getSidebarData() plus the private loadProjectContext()
 * (carried verbatim, the established duplication precedent) - no cross-calls to
 * other ResearchController methods existed, so the move is a verbatim lift.
 */
class ResearchProjectOutputsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    /**
     * Resolve [project, researcher] for a project id. Copied verbatim from
     * ResearchController (issue #1269 project-subsystem extraction). The sibling
     * ResearchProjectsController carries its own identical copy.
     */
    protected function loadProjectContext(int $id): array
    {
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        // SECURITY (#1308): authorize, do not just load. The owner is stored as a
        // collaborator row (role='owner', status='accepted'), so an accepted
        // membership check covers owner + collaborators and excludes pending
        // invites. Mirrors ProjectService::getProjects(). Admins bypass.
        $hasAccess = DB::table('research_project_collaborator')
            ->where('project_id', $id)
            ->where('researcher_id', $researcher->id)
            ->where('status', 'accepted')
            ->exists();
        if (!$hasAccess && !\AhgCore\Services\AclService::isAdministrator(Auth::user())) {
            abort(403, 'You do not have access to this project.');
        }

        return [$project, $researcher];
    }

    public function knowledgeGraph(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        // API endpoint for graph data
        if ($request->wantsJson()) {
            $assertions = DB::table('research_assertion as a')
                ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $id)
                ->select('a.*', DB::raw('COUNT(e.id) as evidence_count'))
                ->groupBy('a.id')
                ->get();

            $nodes = [];
            $edges = [];
            foreach ($assertions as $a) {
                $nodes[] = ['id' => $a->id, 'label' => $a->subject_label ?? '', 'type' => $a->assertion_type ?? '', 'status' => $a->status ?? ''];
                if ($a->object_label ?? null) {
                    $edges[] = ['source' => $a->id, 'target' => $a->object_label, 'label' => $a->predicate ?? ''];
                }
            }
            return response()->json(['nodes' => $nodes, 'edges' => $edges]);
        }

        return view('research::research.knowledge-graph', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    public function assertions(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $typeFilter = $request->input('type');
        $statusFilter = $request->input('status');

        $query = DB::table('research_assertion as a')
            ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
            ->where('a.project_id', $id)
            ->select('a.*', DB::raw('COUNT(e.id) as evidence_count'))
            ->groupBy('a.id');

        if ($typeFilter) $query->where('a.assertion_type', $typeFilter);
        if ($statusFilter) $query->where('a.status', $statusFilter);

        $assertions = $query->orderBy('a.created_at', 'desc')->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            DB::table('research_assertion')->insert([
                'project_id'     => $id,
                'researcher_id'  => $researcher->id,
                'assertion_type' => $request->input('assertion_type', 'biographical'),
                'subject_type'   => 'text',
                'subject_id'     => 0,
                'subject_label'  => $request->input('subject'),
                'predicate'      => $request->input('predicate'),
                'object_value'   => $request->input('object'),
                'object_label'   => $request->input('object'),
                'confidence'     => $request->input('confidence', 0.5),
                'status'         => 'proposed',
                'created_at'     => now(),
            ]);
            return redirect()->route('research.assertions', $id)->with('success', 'Assertion created.');
        }

        return view('research::research.assertions', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'assertions', 'typeFilter', 'statusFilter')
        ));
    }

    public function hypotheses(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $hypotheses = DB::table('research_hypothesis')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            DB::table('research_hypothesis')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'statement'     => $request->input('statement', $request->input('title', '')),
                'tags'          => $request->input('tags'),
                'status'        => 'proposed',
                'created_at'    => now(),
            ]);
            return redirect()->route('research.hypotheses', $id)->with('success', 'Hypothesis created.');
        }

        return view('research::research.hypotheses', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'hypotheses')
        ));
    }

    public function extractionJobs(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        // Handle POST actions
        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $params = [];
                if ($request->input('language')) $params['language'] = $request->input('language');
                if ($request->input('model')) $params['model'] = $request->input('model');

                DB::table('research_extraction_job')->insert([
                    'project_id'      => $id,
                    'collection_id'   => $request->input('collection_id'),
                    'researcher_id'   => $researcher->id,
                    'extraction_type' => $request->input('extraction_type', 'ner'),
                    'parameters_json' => !empty($params) ? json_encode($params) : null,
                    'status'          => 'queued',
                    'total_items'     => 0,
                    'processed_items' => 0,
                    'created_at'      => now(),
                ]);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Extraction job created.');
            }

            if ($action === 'cancel' && $request->input('job_id')) {
                DB::table('research_extraction_job')
                    ->where('id', $request->input('job_id'))
                    ->where('project_id', $id)
                    ->whereIn('status', ['queued', 'running'])
                    ->update(['status' => 'cancelled']);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Job cancelled.');
            }

            if ($action === 'retry' && $request->input('job_id')) {
                DB::table('research_extraction_job')
                    ->where('id', $request->input('job_id'))
                    ->where('project_id', $id)
                    ->where('status', 'failed')
                    ->update(['status' => 'queued']);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Job re-queued.');
            }
        }

        $statusFilter = $request->input('status');
        $query = DB::table('research_extraction_job')
            ->where('project_id', $id);
        if ($statusFilter) $query->where('status', $statusFilter);

        $jobs = $query->orderBy('created_at', 'desc')->get()->toArray();

        return view('research::research.extraction-jobs', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'jobs')
        ));
    }

    public function snapshots(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $snapshots = DB::table('research_snapshot')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $snapshotId = DB::table('research_snapshot')->insertGetId([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'title'         => $request->input('title', 'Snapshot ' . date('Y-m-d H:i')),
                'description'   => $request->input('description'),
                'status'        => 'active',
                'created_at'    => now(),
            ]);
            return redirect()->route('research.snapshots', $id)->with('success', 'Snapshot created.');
        }

        return view('research::research.snapshots', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'snapshots')
        ));
    }

    public function viewSnapshot(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $snapshot = DB::table('research_snapshot')->where('id', $id)->first();
        if (!$snapshot) abort(404, 'Snapshot not found');

        $project = DB::table('research_project')->where('id', $snapshot->project_id)->first();
        if (!$project) abort(404);

        $items = DB::table('research_snapshot_item as si')
            ->leftJoin('information_object_i18n as ioi18n', function ($j) {
                $j->on('si.object_id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
            })
            ->where('si.snapshot_id', $id)
            ->select('si.*', 'ioi18n.title as object_title')
            ->orderBy('si.sort_order')
            ->get()->toArray();

        return view('research::research.view-snapshot', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'snapshot', 'items')
        ));
    }

    public function assertionBatchReview(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $assertions = DB::table('research_assertion')
            ->where('project_id', $id)
            ->where('status', 'proposed')
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'batch_update') {
            $ids = $request->input('assertion_ids', []);
            $newStatus = $request->input('new_status', 'verified');
            if (!empty($ids)) {
                DB::table('research_assertion')
                    ->whereIn('id', $ids)
                    ->where('project_id', $id)
                    ->update(['status' => $newStatus, 'updated_at' => now()]);
            }
            return redirect()->route('research.assertionBatchReview', $id)->with('success', count($ids) . ' assertions updated.');
        }

        return view('research::research.assertion-batch-review', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'assertions')
        ));
    }

    // =========================================================================
    // PROJECT VISUALIZATION
    // =========================================================================

    public function timelineBuilder(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $events = DB::table('research_timeline_event')
                ->where('project_id', $id)
                ->orderBy('date_start')
                ->get();
            return response()->json($events);
        }

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'update_event' && $request->input('event_id')) {
                $update = ['date_start' => $request->input('date_start')];
                if ($request->has('date_end')) $update['date_end'] = $request->input('date_end') ?: null;
                if ($request->has('label')) $update['label'] = $request->input('label');
                if ($request->has('description')) $update['description'] = $request->input('description');
                if ($request->has('date_type')) $update['date_type'] = $request->input('date_type');
                DB::table('research_timeline_event')
                    ->where('id', $request->input('event_id'))
                    ->where('project_id', $id)
                    ->update($update);
                return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event updated.');
            }

            if ($action === 'delete_event' && $request->input('event_id')) {
                DB::table('research_timeline_event')
                    ->where('id', $request->input('event_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event deleted.');
            }

            if ($action === 'auto_populate' && $request->input('collection_id')) {
                // Auto-populate from collection items that have dates
                $collectionId = (int) $request->input('collection_id');
                $items = DB::table('information_object as io')
                    ->join('information_object_i18n as ioi18n', function ($j) {
                        $j->on('io.id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
                    })
                    ->join('event', 'event.object_id', '=', 'io.id')
                    ->where('io.parent_id', $collectionId)
                    ->whereNotNull('event.start_date')
                    ->select('ioi18n.title', 'event.start_date', 'event.end_date', 'event.type_id')
                    ->get();

                $count = 0;
                foreach ($items as $item) {
                    if (!$item->start_date) continue;
                    DB::table('research_timeline_event')->insert([
                        'project_id'    => $id,
                        'researcher_id' => $researcher->id,
                        'label'         => $item->title ?: 'Untitled',
                        'date_start'    => $item->start_date,
                        'date_end'      => $item->end_date ?: null,
                        'date_type'     => 'event',
                        'created_at'    => now(),
                    ]);
                    $count++;
                }
                return redirect()->route('research.timelineBuilder', $id)->with('success', "Added {$count} events from collection.");
            }

            // Default: create new event (only if we have required data)
            $label = $request->input('title', $request->input('label', ''));
            $dateStart = $request->input('event_date', $request->input('date_start'));
            if (!$label || !$dateStart) {
                return redirect()->route('research.timelineBuilder', $id)->with('error', 'Label and start date are required.');
            }

            DB::table('research_timeline_event')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'label'         => $label,
                'description'   => $request->input('description'),
                'date_start'    => $dateStart,
                'date_end'      => $request->input('date_end') ?: null,
                'date_type'     => $request->input('event_type', $request->input('date_type', 'event')),
                'created_at'    => now(),
            ]);
            return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event added.');
        }

        $events = DB::table('research_timeline_event')
            ->where('project_id', $id)
            ->orderBy('date_start')
            ->get()->toArray();

        return view('research::research.timeline-builder', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'events')
        ));
    }

    public function mapBuilder(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $points = DB::table('research_map_point')
                ->where('project_id', $id)
                ->get();
            return response()->json($points);
        }

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'update_point' && $request->input('point_id')) {
                DB::table('research_map_point')
                    ->where('id', $request->input('point_id'))
                    ->where('project_id', $id)
                    ->update([
                        'label'       => $request->input('label'),
                        'place_name'  => $request->input('place_name'),
                        'latitude'    => $request->input('latitude'),
                        'longitude'   => $request->input('longitude'),
                        'description' => $request->input('description'),
                    ]);
                return redirect()->route('research.mapBuilder', $id)->with('success', 'Point updated.');
            }

            if ($action === 'delete_point' && $request->input('point_id')) {
                DB::table('research_map_point')
                    ->where('id', $request->input('point_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.mapBuilder', $id)->with('success', 'Point deleted.');
            }

            // Default: create
            DB::table('research_map_point')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'label'         => $request->input('label', ''),
                'description'   => $request->input('description'),
                'latitude'      => $request->input('latitude'),
                'longitude'     => $request->input('longitude'),
                'place_name'    => $request->input('place_name'),
                'created_at'    => now(),
            ]);
            return redirect()->route('research.mapBuilder', $id)->with('success', 'Point added.');
        }

        $points = DB::table('research_map_point')
            ->where('project_id', $id)
            ->get()->toArray();

        return view('research::research.map-builder', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'points')
        ));
    }

    public function networkGraph(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $assertions = DB::table('research_assertion')
                ->where('project_id', $id)
                ->get();
            $nodes = [];
            $edges = [];
            $nodeMap = [];
            foreach ($assertions as $a) {
                if ($a->subject_label && !isset($nodeMap[$a->subject_label])) {
                    $nodeMap[$a->subject_label] = count($nodes);
                    $nodes[] = ['id' => $a->subject_label, 'label' => $a->subject_label, 'group' => $a->assertion_type ?? 'default'];
                }
                if ($a->object_label && !isset($nodeMap[$a->object_label])) {
                    $nodeMap[$a->object_label] = count($nodes);
                    $nodes[] = ['id' => $a->object_label, 'label' => $a->object_label, 'group' => $a->assertion_type ?? 'default'];
                }
                if ($a->subject_label && $a->object_label) {
                    $edges[] = ['from' => $a->subject_label, 'to' => $a->object_label, 'label' => $a->predicate ?? ''];
                }
            }
            return response()->json(['nodes' => $nodes, 'edges' => $edges]);
        }

        return view('research::research.network-graph', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    // =========================================================================
    // PROJECT RESEARCH OUTPUT
    // =========================================================================

    public function roCrate(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        $resources = DB::table('research_project_resource')
            ->where('project_id', $id)
            ->get()->toArray();

        // Build RO-Crate manifest
        $manifest = [
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph' => [
                ['@type' => 'CreativeWork', '@id' => 'ro-crate-metadata.json', 'conformsTo' => ['@id' => 'https://w3id.org/ro/crate/1.1']],
                [
                    '@type' => 'Dataset',
                    '@id' => './',
                    'name' => $project->title,
                    'description' => $project->description ?? '',
                    'dateCreated' => $project->created_at ?? '',
                    'author' => array_map(fn($c) => ['@type' => 'Person', 'name' => $c->first_name . ' ' . $c->last_name], $collaborators),
                ],
            ],
        ];

        return view('research::research.ro-crate', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'manifest', 'collaborators', 'resources')
        ));
    }

    public function reproducibilityPack(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $milestones = DB::table('research_project_milestone')->where('project_id', $id)->orderBy('sort_order')->get()->toArray();
        $resources = DB::table('research_project_resource')->where('project_id', $id)->get()->toArray();
        $assertions = DB::table('research_assertion')->where('project_id', $id)->get()->toArray();
        $hypotheses = DB::table('research_hypothesis')->where('project_id', $id)->get()->toArray();
        $snapshots = DB::table('research_snapshot')->where('project_id', $id)->get()->toArray();
        $extractionJobs = DB::table('research_extraction_job')->where('project_id', $id)->get()->toArray();

        $searchQueries = [];
        try {
            $searchQueries = DB::table('research_saved_search')->where('researcher_id', $researcher->id)->get()->toArray();
        } catch (\Exception $e) {}

        // JSON download
        if ($request->input('format') === 'json') {
            return response()->json([
                'project' => $project,
                'milestones' => $milestones,
                'resources' => $resources,
                'assertions' => $assertions,
                'hypotheses' => $hypotheses,
                'snapshots' => $snapshots,
                'extraction_jobs' => $extractionJobs,
                'search_queries' => $searchQueries,
                'integrity_hash' => hash('sha256', json_encode([$project->id, count($assertions), count($snapshots), count($milestones)])),
            ]);
        }

        return view('research::research.reproducibility-pack', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'milestones', 'resources', 'assertions', 'hypotheses', 'snapshots', 'extractionJobs', 'searchQueries')
        ));
    }

    public function mintDoi(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('r.first_name', 'r.last_name')
            ->get();

        $creatorsString = $collaborators->map(fn($c) => $c->first_name . ' ' . $c->last_name)->implode(', ');
        $currentDoi = $project->doi ?? null;
        $doiMintedAt = $project->doi_minted_at ?? null;

        if ($request->isMethod('post')) {
            // DOI minting would integrate with DataCite API
            $doi = '10.5281/heratio.' . $project->id . '.' . time();
            DB::table('research_project')->where('id', $id)->update([
                'doi' => $doi,
                'doi_minted_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'doi' => $doi]);
            }
            return redirect()->route('research.mintDoi', $id)->with('success', 'DOI minted: ' . $doi);
        }

        return view('research::research.mint-doi', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'creatorsString', 'currentDoi', 'doiMintedAt')
        ));
    }

    public function ethicsMilestones(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $maxSort = DB::table('research_project_milestone')->where('project_id', $id)->max('sort_order') ?? 0;
                DB::table('research_project_milestone')->insert([
                    'project_id'  => $id,
                    'title'       => $request->input('title'),
                    'description' => $request->input('description'),
                    'status'      => 'pending',
                    'sort_order'  => $maxSort + 1,
                    'created_at'  => now(),
                ]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone added.');
            }

            if ($action === 'edit') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update([
                        'title'       => $request->input('title'),
                        'description' => $request->input('description'),
                        'due_date'    => $request->input('due_date') ?: null,
                    ]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone updated.');
            }

            if ($action === 'approve') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'approved']);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone approved.');
            }

            if ($action === 'reject') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'rejected']);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone rejected.');
            }

            if ($action === 'complete') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => Auth::id()]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone completed.');
            }

            if ($action === 'delete') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone deleted.');
            }
        }

        $milestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        return view('research::research.ethics-milestones', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'milestones')
        ));
    }

    public function complianceDashboard(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $ethicsMilestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        // Compute ethics status
        $ethicsStatus = 'not_started';
        if (!empty($ethicsMilestones)) {
            $statuses = array_column($ethicsMilestones, 'status');
            if (in_array('rejected', $statuses)) $ethicsStatus = 'rejected';
            elseif (in_array('pending', $statuses)) $ethicsStatus = 'pending';
            elseif (count(array_filter($statuses, fn($s) => in_array($s, ['approved', 'completed']))) === count($statuses)) $ethicsStatus = 'approved';
            else $ethicsStatus = 'pending';
        }

        $odrlPolicies = DB::table('research_rights_policy')
            ->where('target_type', 'project')
            ->where('target_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();
        $odrlPolicyCount = count($odrlPolicies);

        // Sensitivity breakdown from linked resources
        $sensitivityBreakdown = [];
        $sensitivitySummary = ['max_level' => 'none'];
        try {
            $resourceObjectIds = DB::table('research_project_resource')
                ->where('project_id', $id)
                ->whereNotNull('object_id')
                ->pluck('object_id');
            if ($resourceObjectIds->isNotEmpty()) {
                $classifications = DB::table('object_security_classification')
                    ->whereIn('object_id', $resourceObjectIds)
                    ->get();
                foreach ($classifications as $c) {
                    $level = $c->classification_level ?? 'unclassified';
                    $sensitivityBreakdown[$level] = ($sensitivityBreakdown[$level] ?? 0) + 1;
                }
                $levelOrder = ['top_secret' => 4, 'secret' => 3, 'confidential' => 2, 'unclassified' => 1, 'none' => 0];
                $maxLevel = 'none';
                foreach ($sensitivityBreakdown as $level => $count) {
                    if (($levelOrder[$level] ?? 0) > ($levelOrder[$maxLevel] ?? 0)) $maxLevel = $level;
                }
                $sensitivitySummary['max_level'] = $maxLevel;
            }
        } catch (\Exception $e) {}

        return view('research::research.compliance-dashboard', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'ethicsMilestones', 'ethicsStatus', 'odrlPolicies', 'odrlPolicyCount', 'sensitivityBreakdown', 'sensitivitySummary')
        ));
    }
}
