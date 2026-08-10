<?php

/**
 * RendersResearchSidebar - shared research-workspace sidebar view data.
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
use Illuminate\Support\Facades\Schema;

/**
 * Build the {sidebarActive, unreadNotifications} view data for the research
 * workspace sidebar. This method was copy-pasted (in three trivially
 * different shapes - sidebar(), sidebar($active), sidebar($active='projects'))
 * into ~20 research controllers; they all compute the same unread-notification
 * count and now share this one, call-site-compatible version.
 *
 * The using controller must expose `$this->research` (the research service
 * with getResearcherByUserId()), which all research controllers already do.
 */
trait RendersResearchSidebar
{
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            if (Auth::check() && Schema::hasTable('research_notification')) {
                $researcher = $this->research->getResearcherByUserId(Auth::id());
                if ($researcher) {
                    $unread = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                }
            }
        } catch (\Throwable $e) {
            // table may not exist yet - leave unread at 0
        }

        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }
}
