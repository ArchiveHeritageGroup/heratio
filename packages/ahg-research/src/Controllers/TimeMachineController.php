<?php

/**
 * TimeMachineController - Controller for Heratio
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
use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Services\TimeMachineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TimeMachineController - Research OS moonshot 19 (heratio#1240). The honesty engine.
 *
 * Per-project, READ-ONLY reconstruction of how the research developed over time,
 * built entirely from timestamped / versioned data other slices already record.
 *
 * Two views:
 *   index  - a merged project timeline grouped by month (newest- or oldest-first).
 *   asOf   - a date scrubber that renders the project state as it stood on or
 *            before a chosen date.
 *
 * Auth-gated. Every action resolves the project defensively and never 500s - a
 * missing project aborts 404, a missing slice contributes nothing, and an empty
 * project renders a friendly empty state.
 */
class TimeMachineController extends Controller
{
    use AuthorizesProjectAccess;

    protected TimeMachineService $machine;

    public function __construct()
    {
        $this->machine = new TimeMachineService();
    }

    /** Resolve the project row defensively. */
    protected function project(int $projectId): object
    {
        try {
            $project = DB::table('research_project')->where('id', $projectId)->first();
        } catch (\Throwable $e) {
            $project = null;
        }
        if (! $project) {
            abort(404, 'Project not found');
        }
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectAccess($projectId);
        return $project;
    }

    /** Build the shared sidebar payload (matches the rest of the research portal). */
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            $userId = Auth::id();
            if ($userId) {
                $researcher = DB::table('research_researcher')->where('user_id', $userId)->first();
                if ($researcher) {
                    $unread = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                }
            }
        } catch (\Throwable $e) {
            // tables may not exist yet
        }
        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }

    /** Project timeline grouped by month. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->project($projectId);

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $events  = $this->machine->timeline($projectId, $order);
        $grouped = $this->machine->groupByMonth($events);
        [$min, $max] = $this->machine->bounds($events);

        return view('research::research.timemachine.index', array_merge(
            $this->sidebar('projects'),
            [
                'project'    => $project,
                'projectId'  => $projectId,
                'events'     => $events,
                'grouped'    => $grouped,
                'order'      => $order,
                'eventCount' => count($events),
                'minDate'    => $min,
                'maxDate'    => $max,
                'kindBadges' => TimeMachineService::KIND_BADGES,
                'kindLabels' => TimeMachineService::KIND_LABELS,
            ]
        ));
    }

    /** State as of a chosen date (date scrubber). */
    public function asOf(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->project($projectId);

        // Bounds drive the scrubber min/max and the default.
        $events = $this->machine->timeline($projectId, 'asc');
        [$min, $max] = $this->machine->bounds($events);

        // The requested date is parsed defensively inside the service; an invalid
        // or empty value resolves to "now", so the snapshot always renders.
        $requested = (string) $request->input('date', '');
        if ($requested === '' && $max) {
            $requested = $max->format('Y-m-d');
        }

        $state = $this->machine->stateAsOf($projectId, $requested);

        return view('research::research.timemachine.as-of', array_merge(
            $this->sidebar('projects'),
            [
                'project'      => $project,
                'projectId'    => $projectId,
                'state'        => $state,
                'requested'    => $requested,
                'asOf'         => $state['asOf'],
                'minDate'      => $min,
                'maxDate'      => $max,
                'kindBadges'   => TimeMachineService::KIND_BADGES,
                'kindLabels'   => TimeMachineService::KIND_LABELS,
            ]
        ));
    }
}
