<?php

/**
 * ResearchControllerHelpers - Shared controller helpers for Heratio
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



namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchControllerHelpers - Shared helpers for the research portal controllers.
 *
 * Extracted (stage 3 of the monolith decomposition, issue #1253) from
 * ResearchController so that the controllers split out of the monolith
 * (ResearchReproductionsController, ResearchAnnotationsController, and any
 * future ones) reuse a single canonical copy of the sidebar/researcher
 * helpers instead of duplicating them.
 *
 * CONTRACT: the consuming class MUST expose a `protected ResearchService $service`
 * property (the canonical AhgResearch\Services\ResearchService). Both the
 * sidebar curation (experience_level self-lookup + unread notification count)
 * and the researcher resolution depend on it.
 */
trait ResearchControllerHelpers
{
    /**
     * Resolve the current researcher or return a redirect response.
     *
     * - Not logged in -> redirect to login.
     * - Logged in but no researcher profile -> redirect to researcher register.
     * - Otherwise -> the researcher row.
     *
     * Callers must check the return type (redirect vs researcher object).
     */
    protected function getResearcherOrRedirect()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) {
            return redirect()->route('researcher.register');
        }
        return $researcher;
    }

    /**
     * Sidebar data shared with the research portal layout.
     *
     * Includes the active item key, the unread notification count for the
     * current researcher, and the curated experience_level (defaults to
     * 'intermediate' when not logged in or not set on the profile).
     */
    protected function getSidebarData(string $active): array
    {
        $unreadNotifications = 0;
        $experienceLevel = 'intermediate';
        if (Auth::check()) {
            $researcher = $this->service->getResearcherByUserId(Auth::id());
            if ($researcher) {
                try {
                    $unreadNotifications = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                } catch (\Exception $e) {
                    // Table may not exist yet
                }
                if (!empty($researcher->experience_level)) {
                    $experienceLevel = $researcher->experience_level;
                }
            }
        }
        return [
            'sidebarActive' => $active,
            'unreadNotifications' => $unreadNotifications,
            'experienceLevel' => $experienceLevel,
        ];
    }
}
