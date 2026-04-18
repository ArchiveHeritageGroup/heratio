<?php

/**
 * ReportService - Service for Heratio
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



namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;

/**
 * ReportService - Research Report Management
 *
 * CRUD for reports and sections, template-based creation,
 * auto-population from projects, and HTML rendering.
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/ReportService.php
 */
class ReportService
{
    /**
     * Create a new report.
     */
    public function createReport(int $researcherId, array $data): int
    {
        return DB::table('research_report')->insertGetId([
            'researcher_id' => $researcherId,
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'template_type' => $data['template_type'] ?? 'custom',
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a report from a template.
     */
    public function createFromTemplate(int $researcherId, string $templateCode, array $data): int
    {
        $template = DB::table('research_report_template')
            ->where('code', $templateCode)
            ->first();

        $data['template_type'] = $templateCode;
        $reportId = $this->createReport($researcherId, $data);

        if ($template) {
            $sections = json_decode($template->sections_config, true) ?: [];
            $order = 0;
            foreach ($sections as $sectionDef) {
                $parts = explode(':', $sectionDef, 2);
                $type = $parts[0];
                $title = $parts[1] ?? ucfirst($type);

                DB::table('research_report_section')->insert([
                    'report_id' => $reportId,
                    'section_type' => in_array($type, ['title_page', 'toc', 'heading', 'text', 'bibliography', 'collection_list', 'annotation_list', 'timeline', 'custom']) ? $type : 'text',
                    'title' => $title,
                    'content' => '',
                    'content_format' => 'html',
                    'sort_order' => $order++,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $reportId;
    }

    /**
     * Get a report with its sections.
     */
    public function getReport(int $id): ?object
    {
        $report = DB::table('research_report as r')
            ->leftJoin('research_researcher as res', 'r.researcher_id', '=', 'res.id')
            ->leftJoin('research_project as p', 'r.project_id', '=', 'p.id')
            ->where('r.id', $id)
            ->select('r.*', 'res.first_name', 'res.last_name', 'p.title as project_title')
            ->first();

        if ($report) {
            $report->sections = $this->getSections($id);
        }

        return $report;
    }

    /**
     * Get report sections ordered by sort_order.
     */
    public function getSections(int $reportId): array
    {
        return DB::table('research_report_section')
            ->where('report_id', $reportId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Update a report.
     */
    public function updateReport(int $id, array $data): bool
    {
        $update = [];
        foreach (['title', 'description', 'status', 'project_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (isset($data['metadata'])) {
            $update['metadata'] = json_encode($data['metadata']);
        }
        $update['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('research_report')->where('id', $id)->update($update) > 0;
    }

    /**
     * Delete a report and its sections.
     */
    public function deleteReport(int $id, int $researcherId): bool
    {
        $report = DB::table('research_report')
            ->where('id', $id)
            ->where('researcher_id', $researcherId)
            ->first();

        if (!$report) {
            return false;
        }

        DB::table('research_report_section')->where('report_id', $id)->delete();
        DB::table('research_report')->where('id', $id)->delete();

        return true;
    }

    /**
     * Get reports for a researcher.
     */
    public function getReports(int $researcherId, array $filters = []): array
    {
        $query = DB::table('research_report as r')
            ->leftJoin('research_project as p', 'r.project_id', '=', 'p.id')
            ->where('r.researcher_id', $researcherId)
            ->select('r.*', 'p.title as project_title', DB::raw('(SELECT COUNT(*) FROM research_report_section WHERE report_id = r.id) as section_count'));

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('r.project_id', $filters['project_id']);
        }

        return $query->orderBy('r.updated_at', 'desc')->get()->toArray();
    }

    /**
     * Add a section to a report.
     */
    public function addSection(int $reportId, array $data): int
    {
        $maxOrder = DB::table('research_report_section')
            ->where('report_id', $reportId)
            ->max('sort_order') ?? -1;

        return DB::table('research_report_section')->insertGetId([
            'report_id' => $reportId,
            'section_type' => $data['section_type'] ?? 'text',
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? '',
            'content_format' => $data['content_format'] ?? 'html',
            'bibliography_id' => $data['bibliography_id'] ?? null,
            'collection_id' => $data['collection_id'] ?? null,
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update a section.
     */
    public function updateSection(int $sectionId, array $data): bool
    {
        $update = [];
        foreach (['title', 'content', 'content_format', 'section_type', 'bibliography_id', 'collection_id', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (isset($data['settings'])) {
            $update['settings'] = json_encode($data['settings']);
        }
        $update['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('research_report_section')->where('id', $sectionId)->update($update) > 0;
    }

    /**
     * Delete a section.
     */
    public function deleteSection(int $sectionId): bool
    {
        return DB::table('research_report_section')->where('id', $sectionId)->delete() > 0;
    }

    /**
     * Move a section up or down.
     */
    public function moveSection(int $sectionId, string $direction): void
    {
        $section = DB::table('research_report_section')->where('id', $sectionId)->first();
        if (!$section) return;

        $sections = DB::table('research_report_section')
            ->where('report_id', $section->report_id)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $currentIndex = null;
        foreach ($sections as $i => $s) {
            if ($s->id == $sectionId) { $currentIndex = $i; break; }
        }
        if ($currentIndex === null) return;

        $swapIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if ($swapIndex < 0 || $swapIndex >= count($sections)) return;

        $currentOrder = $sections[$currentIndex]->sort_order;
        $swapOrder = $sections[$swapIndex]->sort_order;

        DB::table('research_report_section')->where('id', $sections[$currentIndex]->id)->update(['sort_order' => $swapOrder]);
        DB::table('research_report_section')->where('id', $sections[$swapIndex]->id)->update(['sort_order' => $currentOrder]);
    }

    /**
     * Reorder sections via array of IDs.
     */
    public function reorderSections(int $reportId, array $sectionIds): void
    {
        foreach ($sectionIds as $order => $sectionId) {
            DB::table('research_report_section')
                ->where('id', $sectionId)
                ->where('report_id', $reportId)
                ->update(['sort_order' => $order]);
        }
        DB::table('research_report')->where('id', $reportId)->update(['updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Auto-populate report sections from project data.
     */
    public function autoPopulateFromProject(int $reportId, int $projectId): void
    {
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (!$project) {
            return;
        }

        // Update title page section if it exists
        DB::table('research_report_section')
            ->where('report_id', $reportId)
            ->where('section_type', 'title_page')
            ->update([
                'content' => '<h1>' . htmlspecialchars($project->title) . '</h1>' .
                    ($project->description ? '<p>' . htmlspecialchars($project->description) . '</p>' : '') .
                    ($project->institution ? '<p><strong>Institution:</strong> ' . htmlspecialchars($project->institution) . '</p>' : '') .
                    ($project->supervisor ? '<p><strong>Supervisor:</strong> ' . htmlspecialchars($project->supervisor) . '</p>' : ''),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        DB::table('research_report')
            ->where('id', $reportId)
            ->update(['project_id' => $projectId, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Render full report as HTML for display or export.
     */
    public function renderReportHtml(int $reportId): string
    {
        $report = $this->getReport($reportId);
        if (!$report) {
            return '';
        }

        $html = '<div class="research-report">';
        foreach ($report->sections as $section) {
            $html .= $this->renderSectionHtml($section, $report);
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single section as HTML.
     */
    protected function renderSectionHtml(object $section, object $report): string
    {
        $html = '<div class="report-section" data-type="' . htmlspecialchars($section->section_type) . '">';

        switch ($section->section_type) {
            case 'title_page':
                $html .= '<div class="title-page">';
                $html .= $section->content ?: '<h1>' . htmlspecialchars($report->title) . '</h1>';
                $html .= '<p class="text-muted">' . htmlspecialchars($report->first_name . ' ' . $report->last_name) . '</p>';
                $html .= '<p class="text-muted">' . date('F j, Y', strtotime($report->created_at)) . '</p>';
                $html .= '</div>';
                break;

            case 'toc':
                $html .= '<h2>Table of Contents</h2><ol class="toc-list">';
                foreach ($report->sections as $s) {
                    if ($s->section_type !== 'toc' && $s->section_type !== 'title_page' && $s->title) {
                        $html .= '<li>' . htmlspecialchars($s->title) . '</li>';
                    }
                }
                $html .= '</ol>';
                break;

            case 'heading':
                $html .= '<h2>' . htmlspecialchars($section->title ?? '') . '</h2>';
                break;

            case 'bibliography':
                $html .= '<h2>' . htmlspecialchars($section->title ?? 'Bibliography') . '</h2>';
                if ($section->bibliography_id) {
                    $entries = DB::table('research_bibliography_entry')
                        ->where('bibliography_id', $section->bibliography_id)
                        ->orderBy('sort_order')
                        ->get();
                    $html .= '<ol class="bibliography-list">';
                    foreach ($entries as $entry) {
                        $html .= '<li>' . htmlspecialchars($entry->title ?? '') . '</li>';
                    }
                    $html .= '</ol>';
                }
                $html .= $section->content ?? '';
                break;

            case 'collection_list':
                $html .= '<h2>' . htmlspecialchars($section->title ?? 'Collection Items') . '</h2>';
                if ($section->collection_id) {
                    $items = DB::table('research_collection_item as ci')
                        ->leftJoin('information_object_i18n as ioi', function ($join) {
                            $join->on('ci.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                        })
                        ->where('ci.collection_id', $section->collection_id)
                        ->orderBy('ci.sort_order')
                        ->select('ci.*', 'ioi.title as item_title')
                        ->get();
                    $html .= '<ul>';
                    foreach ($items as $item) {
                        $html .= '<li>' . htmlspecialchars($item->item_title ?? 'Untitled') . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= $section->content ?? '';
                break;

            default:
                if ($section->title) {
                    $html .= '<h3>' . htmlspecialchars($section->title) . '</h3>';
                }
                $html .= $section->content ?? '';
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get available templates.
     */
    public function getTemplates(): array
    {
        return DB::table('research_report_template')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
