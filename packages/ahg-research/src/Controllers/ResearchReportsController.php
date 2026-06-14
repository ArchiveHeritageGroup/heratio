<?php

/**
 * ResearchReportsController - Controller for Heratio
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
 * ResearchReportsController - Researcher report builder (sectioned reports).
 *
 * Extracted from ResearchController as stage 5 of the monolith decomposition
 * (issue #1253 / #1269). All three endpoints are auth-gated and operate on the
 * current researcher's own reports, report sections, and report templates via
 * the research_report* tables. No cross-calls to other ResearchController
 * methods existed - the methods used only the shared trait helpers
 * (getSidebarData) and the injected ResearchService (sanitizeHtml +
 * getResearcherByUserId), so the move is a verbatim lift.
 */
class ResearchReportsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function reports(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $reportId = DB::table('research_report')->insertGetId([
                'researcher_id' => $researcher->id,
                'project_id' => $request->input('project_id') ?: null,
                'title' => $request->input('title'),
                'template_type' => $request->input('template_type', 'custom'),
                'description' => $request->input('description') ?: null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-create sections from template config in DB
            $template = DB::table('research_report_template')
                ->where('code', $request->input('template_type', 'custom'))
                ->first();

            if ($template && $template->sections_config) {
                $sectionConfigs = json_decode($template->sections_config, true) ?: [];
                foreach ($sectionConfigs as $i => $cfg) {
                    $parts = explode(':', $cfg, 2);
                    $type = $parts[0] ?? 'text';
                    $title = $parts[1] ?? ucfirst($type);
                    DB::table('research_report_section')->insert([
                        'report_id' => $reportId,
                        'section_type' => $type,
                        'title' => $title,
                        'sort_order' => $i,
                        'created_at' => now(),
                    ]);
                }
            }

            return redirect()->route('research.viewReport', $reportId)->with('success', 'Report created');
        }

        $query = DB::table('research_report as r')
            ->leftJoin('research_project as p', 'r.project_id', '=', 'p.id')
            ->where('r.researcher_id', $researcher->id);
        $currentStatus = $request->input('status');
        if ($currentStatus) $query->where('r.status', $currentStatus);

        $reports = $query
            ->select('r.*', 'p.title as project_title',
                DB::raw('(SELECT COUNT(*) FROM research_report_section WHERE report_id = r.id) as section_count'))
            ->orderBy('r.updated_at', 'desc')
            ->get()->toArray();

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')->orderBy('p.title')->get()->toArray();

        $templates = DB::table('research_report_template')->orderBy('is_system', 'desc')->orderBy('name')->get()->toArray();

        return view('research::research.reports', array_merge(
            $this->getSidebarData('reports'),
            compact('researcher', 'reports', 'currentStatus', 'projects', 'templates')
        ));
    }

    /**
     * Manage report templates (admin).
     */
    public function reportTemplates(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $code = \Illuminate\Support\Str::slug($request->input('name'), '_');
                $sections = array_filter(array_map('trim', explode("\n", $request->input('sections_raw', ''))));
                DB::table('research_report_template')->insert([
                    'name' => $request->input('name'),
                    'code' => $code,
                    'description' => $request->input('description') ?: null,
                    'sections_config' => json_encode($sections),
                    'is_system' => 0,
                    'created_at' => now(),
                ]);
                return redirect()->route('research.reportTemplates')->with('success', 'Template created.');
            }

            if ($action === 'update') {
                $id = (int) $request->input('template_id');
                $sections = array_filter(array_map('trim', explode("\n", $request->input('sections_raw', ''))));
                DB::table('research_report_template')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description') ?: null,
                    'sections_config' => json_encode($sections),
                ]);
                return redirect()->route('research.reportTemplates')->with('success', 'Template updated.');
            }

            if ($action === 'delete') {
                $id = (int) $request->input('template_id');
                $tpl = DB::table('research_report_template')->where('id', $id)->first();
                if ($tpl && !$tpl->is_system) {
                    DB::table('research_report_template')->where('id', $id)->delete();
                    return redirect()->route('research.reportTemplates')->with('success', 'Template deleted.');
                }
                return redirect()->route('research.reportTemplates')->with('error', 'System templates cannot be deleted.');
            }
        }

        $templates = DB::table('research_report_template')->orderBy('is_system', 'desc')->orderBy('name')->get()->toArray();

        return view('research::research.report-templates', array_merge(
            $this->getSidebarData('reports'),
            compact('templates')
        ));
    }

    public function viewReport(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $report = DB::table('research_report')->where('id', $id)->first();
        if (!$report || $report->researcher_id != $researcher->id) abort(404);

        $sections = DB::table('research_report_section')
            ->where('report_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();
        $report->sections = $sections;

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_section') {
                $maxOrder = DB::table('research_report_section')
                    ->where('report_id', $id)->max('sort_order') ?? -1;
                DB::table('research_report_section')->insert([
                    'report_id' => $id,
                    'section_type' => $request->input('section_type', 'text'),
                    'title' => $request->input('title'),
                    'sort_order' => $maxOrder + 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Section added');
            }

            if ($action === 'update_section') {
                $content = $this->service->sanitizeHtml($request->input('content', ''));
                DB::table('research_report_section')
                    ->where('id', (int) $request->input('section_id'))
                    ->update([
                        'title' => $request->input('title'),
                        'content' => $content,
                        'content_format' => 'html',
                    ]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Section updated');
            }

            if ($action === 'delete_section') {
                DB::table('research_report_section')
                    ->where('id', (int) $request->input('section_id'))
                    ->delete();
                return redirect()->route('research.viewReport', $id)->with('success', 'Section deleted');
            }

            if ($action === 'delete_report') {
                DB::table('research_report_section')->where('report_id', $id)->delete();
                DB::table('research_report')->where('id', $id)->delete();
                return redirect()->route('research.reports')->with('success', 'Report deleted');
            }

            if ($action === 'update_status') {
                DB::table('research_report')->where('id', $id)->update(['status' => $request->input('status'), 'updated_at' => now()]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Status updated');
            }

            if ($action === 'move_section') {
                $sectionId = (int) $request->input('section_id');
                $direction = $request->input('direction');
                $section = DB::table('research_report_section')->where('id', $sectionId)->first();
                if ($section) {
                    $swap = DB::table('research_report_section')
                        ->where('report_id', $id)
                        ->where('sort_order', $direction === 'up' ? '<' : '>', $section->sort_order)
                        ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
                        ->first();
                    if ($swap) {
                        DB::table('research_report_section')->where('id', $section->id)->update(['sort_order' => $swap->sort_order]);
                        DB::table('research_report_section')->where('id', $swap->id)->update(['sort_order' => $section->sort_order]);
                    }
                }
                return redirect()->route('research.viewReport', $id);
            }

            if ($action === 'load_template') {
                $template = DB::table('research_report_template')
                    ->where('code', $request->input('template_code'))
                    ->first();
                if ($template && $template->sections_config) {
                    $maxOrder = DB::table('research_report_section')->where('report_id', $id)->max('sort_order') ?? -1;
                    $configs = json_decode($template->sections_config, true) ?: [];
                    foreach ($configs as $i => $cfg) {
                        $parts = explode(':', $cfg, 2);
                        DB::table('research_report_section')->insert([
                            'report_id' => $id, 'section_type' => $parts[0] ?? 'text',
                            'title' => $parts[1] ?? ucfirst($parts[0] ?? 'Section'),
                            'sort_order' => $maxOrder + 1 + $i, 'created_at' => now(),
                        ]);
                    }
                    return redirect()->route('research.viewReport', $id)->with('success', count($configs) . ' sections loaded from template');
                }
                return redirect()->route('research.viewReport', $id)->with('error', 'Template not found');
            }

            if ($action === 'add_multiple') {
                $types = $request->input('section_types', []);
                $maxOrder = DB::table('research_report_section')->where('report_id', $id)->max('sort_order') ?? -1;
                foreach ($types as $i => $type) {
                    DB::table('research_report_section')->insert([
                        'report_id' => $id, 'section_type' => $type,
                        'title' => ucwords(str_replace('_', ' ', $type)),
                        'sort_order' => $maxOrder + 1 + $i, 'created_at' => now(),
                    ]);
                }
                return redirect()->route('research.viewReport', $id)->with('success', count($types) . ' sections added');
            }

            if ($action === 'add_comment') {
                DB::table('research_discussion')->insert([
                    'workspace_id' => null, 'project_id' => null,
                    'parent_id' => null, 'researcher_id' => $researcher->id,
                    'subject' => 'Comment on section #' . $request->input('section_id'),
                    'content' => $request->input('comment_content'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Comment added');
            }

            if ($action === 'update_header') {
                DB::table('research_report')->where('id', $id)->update([
                    'status' => $request->input('status'),
                    'updated_at' => now(),
                ]);
                return redirect()->route('research.viewReport', $id);
            }
        }

        // Export as printable PDF page
        if ($request->input('export') === 'pdf' || $request->input('export') === '1') {
            return view('research::research.report-pdf', compact('researcher', 'report'));
        }

        return view('research::research.view-report', array_merge(
            $this->getSidebarData('reports'),
            compact('researcher', 'report')
        ));
    }
}
