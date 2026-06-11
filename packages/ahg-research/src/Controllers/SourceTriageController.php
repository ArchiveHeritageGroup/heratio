<?php

/**
 * SourceTriageController - Heratio ahg-research
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
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchService;
use AhgResearch\Services\SourceTriageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1227 - Research OS Stage 5: per-project Source Triage board.
 *
 * Lists the project's sources (bibliography entries + collection items) with their current
 * triage category and read-status, and lets the researcher set category + read-status, add
 * notes, and optionally generate an AI structured preview (always labelled, never verified).
 * The system never auto-marks anything 'read' - read-status only moves on an explicit action.
 */
class SourceTriageController extends Controller
{
    protected ResearchService $research;

    protected SourceTriageService $triage;

    public function __construct()
    {
        $this->research = new ResearchService();
        $this->triage = app(SourceTriageService::class);
    }

    /** The triage board for a project. Never 500s - degrades to an empty-state. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }

        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }

        $sources = $this->triage->getBoard($projectId);

        // Optional filters by category and by read-status (server-side, so deep links work).
        $categoryFilter = (string) $request->query('category', '');
        $readFilter     = (string) $request->query('read', '');
        if ($categoryFilter !== '') {
            $sources = array_values(array_filter($sources, fn ($s) => ($s['triage_category'] ?? '') === $categoryFilter));
        }
        if ($readFilter !== '') {
            $sources = array_values(array_filter($sources, fn ($s) => ($s['read_status'] ?? 'unread') === $readFilter));
        }

        return view('research::research.source-triage', [
            'sidebarActive'   => 'projects',
            'project'         => $project,
            'researcher'      => $researcher,
            'sources'         => $sources,
            'categories'      => $this->triage->categories(),
            'readStatuses'    => $this->triage->readStatuses(),
            'categoryFilter'  => $categoryFilter,
            'readFilter'      => $readFilter,
            'aiPreviewLabel'  => SourceTriageService::AI_PREVIEW_LABEL,
        ]);
    }

    /** Set the triage category for a single source. */
    public function setCategory(Request $request, int $projectId)
    {
        [$researcherId, $project] = $this->guard($projectId);
        $data = $this->validateTarget($request, ['triage_category' => 'nullable|string|max:40']);

        if (! $this->triage->sourceBelongsToProject($projectId, $data['source_type'], (int) $data['source_id'])) {
            return $this->fail($request, $projectId, 'That source is not part of this project.');
        }

        $ok = $this->triage->setCategory(
            $projectId, $data['source_type'], (int) $data['source_id'],
            $data['triage_category'] ?? null, $researcherId
        );

        return $this->done($request, $projectId, $ok, $ok ? 'Triage category updated.' : 'Could not update the category.');
    }

    /**
     * Set the read-status for a single source. This is the only endpoint that moves read-status,
     * and it only ever fires from an explicit researcher action.
     */
    public function setReadStatus(Request $request, int $projectId)
    {
        [$researcherId, $project] = $this->guard($projectId);
        $data = $this->validateTarget($request, ['read_status' => 'required|string|max:40']);

        if (! $this->triage->isValidReadStatus($data['read_status'])) {
            return $this->fail($request, $projectId, 'Unknown read-status.');
        }
        if (! $this->triage->sourceBelongsToProject($projectId, $data['source_type'], (int) $data['source_id'])) {
            return $this->fail($request, $projectId, 'That source is not part of this project.');
        }

        $ok = $this->triage->setReadStatus(
            $projectId, $data['source_type'], (int) $data['source_id'],
            $data['read_status'], $researcherId
        );

        return $this->done($request, $projectId, $ok, $ok ? 'Read-status updated.' : 'Could not update the read-status.');
    }

    /** Save notes against a single source. */
    public function setNotes(Request $request, int $projectId)
    {
        [$researcherId, $project] = $this->guard($projectId);
        $data = $this->validateTarget($request, ['notes' => 'nullable|string|max:5000']);

        if (! $this->triage->sourceBelongsToProject($projectId, $data['source_type'], (int) $data['source_id'])) {
            return $this->fail($request, $projectId, 'That source is not part of this project.');
        }

        $ok = $this->triage->setNotes(
            $projectId, $data['source_type'], (int) $data['source_id'],
            $data['notes'] ?? null, $researcherId
        );

        return $this->done($request, $projectId, $ok, $ok ? 'Notes saved.' : 'Could not save the notes.');
    }

    /**
     * Generate (or refresh) the optional AI structured preview for a source. AI is optional;
     * failure is reported but never fatal. The preview is always shown with its label.
     */
    public function aiPreview(Request $request, int $projectId)
    {
        [$researcherId, $project] = $this->guard($projectId);
        $data = $this->validateTarget($request, []);

        $result = $this->triage->generateAiPreview(
            $projectId, $data['source_type'], (int) $data['source_id'], $researcherId
        );

        if ($request->wantsJson()) {
            return response()->json(array_merge($result, ['label' => SourceTriageService::AI_PREVIEW_LABEL]));
        }

        if (! empty($result['ok'])) {
            return redirect()->route('research.triage.index', $projectId)->with('success', 'AI preview generated. ' . SourceTriageService::AI_PREVIEW_LABEL . '.');
        }

        return redirect()->route('research.triage.index', $projectId)->with('error', $result['error'] ?? 'AI preview unavailable.');
    }

    /** Resolve the researcher + project, or abort/redirect. Returns [researcherId, project]. */
    private function guard(int $projectId): array
    {
        if (! Auth::check()) {
            abort(401);
        }
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }

        return [(int) $researcher->id, $project];
    }

    /** Validate the source target (+ optional extra rules) common to every write endpoint. */
    private function validateTarget(Request $request, array $extra): array
    {
        return $request->validate(array_merge([
            'source_type' => 'required|string|in:bibliography_entry,collection_item',
            'source_id'   => 'required|integer|min:1',
        ], $extra));
    }

    private function fail(Request $request, int $projectId, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 422);
        }

        return redirect()->route('research.triage.index', $projectId)->with('error', $message);
    }

    private function done(Request $request, int $projectId, bool $ok, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message]);
        }

        return redirect()->route('research.triage.index', $projectId)
            ->with($ok ? 'success' : 'error', $message);
    }
}
