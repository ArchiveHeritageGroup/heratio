<?php

/**
 * ArgumentBuilderController - Controller for Heratio
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
use AhgResearch\Services\ArgumentBuilderService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ArgumentBuilderController - Research OS Stage 12 (heratio#1229).
 *
 * Per-project Argument Builder over the existing Claim Ledger. The researcher
 * drags CLAIMS (research_assertion rows) into an ordered nine-step argument and
 * the system warns about weak spots (uncited steps, single-source over-reliance,
 * missing steps, contested claims, conclusions stronger than the evidence).
 *
 * Auth-gated like the rest of the portal; routes carry the 'web'+'auth'
 * middleware and each action resolves the researcher + project context
 * defensively, mirroring ClaimLedgerController / loadProjectContext.
 */
class ArgumentBuilderController extends Controller
{
    protected ArgumentBuilderService $builder;
    protected ResearchService $research;

    public function __construct()
    {
        $this->builder = new ArgumentBuilderService();
        $this->research = new ResearchService();
    }

    /** Resolve [project, researcher] for a project id, mirroring the Claim Ledger. */
    protected function context(int $projectId): array
    {
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }
        return [$project, $researcher];
    }

    /** Build the shared sidebar payload (matches the rest of the research portal). */
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)->count();
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }
        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }

    /** Show the argument canvas + warnings panel for a project. */
    public function show(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $argument       = $this->builder->getOrCreateArgument($projectId, (int) Auth::id());
        $steps          = $argument ? $this->builder->getSteps((int) $argument->id) : [];
        $warnings       = $this->builder->computeWarnings($steps);
        $availableClaims= $this->builder->availableClaims($projectId);

        // Group warnings by slot for inline rendering on the canvas.
        $warningsBySlot = [];
        foreach ($warnings as $w) {
            $key = $w['slot'] ?? '_global';
            $warningsBySlot[$key][] = $w;
        }

        return view('research::research.argument-builder.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'argument', 'steps', 'warnings', 'warningsBySlot', 'availableClaims'),
            ['slots' => ArgumentBuilderService::SLOTS]
        ));
    }

    /** Update the argument header (title + central thesis) (POST). */
    public function update(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'title'          => 'nullable|string|max:255',
            'central_thesis' => 'nullable|string|max:65535',
        ]);

        $argument = $this->builder->getOrCreateArgument($projectId, (int) Auth::id());
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'Could not save the argument.');
        }
        $ok = $this->builder->updateArgument($projectId, (int) $argument->id, $validated);
        return redirect()->route('research.argument.show', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Argument saved.' : 'Could not save the argument.');
    }

    /** Add a step (slot) to the argument (POST). */
    public function addStep(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'slot'         => 'required|string|max:40',
            'assertion_id' => 'nullable|integer',
            'note'         => 'nullable|string|max:2000',
        ]);

        $argument = $this->builder->getOrCreateArgument($projectId, (int) Auth::id());
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'Could not add the step.');
        }

        $id = $this->builder->addStep(
            $projectId,
            (int) $argument->id,
            $validated['slot'],
            isset($validated['assertion_id']) ? (int) $validated['assertion_id'] : null,
            $validated['note'] ?? null
        );

        return redirect()->route('research.argument.show', $projectId)
            ->with($id ? 'success' : 'error', $id ? 'Step added.' : 'Could not add the step (check the slot is valid).');
    }

    /** Attach / change the claim on a step (POST). */
    public function attachClaim(Request $request, int $projectId, int $stepId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'assertion_id' => 'nullable|integer',
        ]);

        $argument = $this->builder->getArgument($projectId);
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'No argument to update.');
        }

        $assertionId = (isset($validated['assertion_id']) && (int) $validated['assertion_id'] > 0)
            ? (int) $validated['assertion_id'] : null;

        $ok = $this->builder->attachClaim($projectId, (int) $argument->id, $stepId, $assertionId);
        return redirect()->route('research.argument.show', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Claim updated on step.' : 'Could not update the claim.');
    }

    /** Update a step's free-text note (POST). */
    public function updateStep(Request $request, int $projectId, int $stepId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $argument = $this->builder->getArgument($projectId);
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'No argument to update.');
        }

        $ok = $this->builder->updateStepNote($projectId, (int) $argument->id, $stepId, $validated['note'] ?? null);
        return redirect()->route('research.argument.show', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Note saved.' : 'Could not save the note.');
    }

    /** Remove a step from the argument (POST). */
    public function removeStep(Request $request, int $projectId, int $stepId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $argument = $this->builder->getArgument($projectId);
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'No argument to update.');
        }

        $ok = $this->builder->removeStep($projectId, (int) $argument->id, $stepId);
        return redirect()->route('research.argument.show', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Step removed.' : 'Could not remove the step.');
    }

    /** Reorder steps from a posted ordered id list (POST). */
    public function reorder(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $order = $request->input('order', []);
        if (is_string($order)) {
            $order = array_filter(explode(',', $order), fn ($v) => $v !== '');
        }
        $order = is_array($order) ? array_map('intval', $order) : [];

        $argument = $this->builder->getArgument($projectId);
        if (! $argument) {
            return redirect()->route('research.argument.show', $projectId)
                ->with('error', 'No argument to reorder.');
        }

        $ok = $this->builder->reorderSteps($projectId, (int) $argument->id, $order);
        return redirect()->route('research.argument.show', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Order updated.' : 'Could not reorder the steps.');
    }
}
