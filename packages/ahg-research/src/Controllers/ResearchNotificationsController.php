<?php

/**
 * ResearchNotificationsController - Controller for Heratio
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
 * ResearchNotificationsController - Researcher in-app notifications + preferences.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The single endpoint is auth-gated and operates on the current
 * researcher's own notifications and notification preferences via the
 * research_notification* tables. The mark_read / mark_all_read / update_preferences
 * POST actions are all handled inside this one method. No cross-calls to other
 * ResearchController methods existed - the method used only the shared trait
 * helper (getSidebarData) and the injected ResearchService (getResearcherByUserId),
 * so the move is a verbatim lift.
 */
class ResearchNotificationsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function notifications(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $action = $request->input('do');
            if ($action === 'mark_read') {
                DB::table('research_notification')
                    ->where('id', (int) $request->input('id'))
                    ->where('researcher_id', $researcher->id)
                    ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
            } elseif ($action === 'mark_all_read') {
                DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)
                    ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
            } elseif ($action === 'update_preferences') {
                $prefs = $request->input('prefs', []);
                foreach ($prefs as $type => $settings) {
                    DB::table('research_notification_preference')->updateOrInsert(
                        ['researcher_id' => $researcher->id, 'notification_type' => $type],
                        [
                            'in_app_enabled' => isset($settings['in_app_enabled']) ? 1 : 0,
                            'email_enabled' => isset($settings['email_enabled']) ? 1 : 0,
                            'digest_frequency' => $settings['digest_frequency'] ?? 'immediate',
                        ]
                    );
                }
                return redirect()->route('research.notifications', ['tab' => 'preferences'])->with('success', 'Preferences saved.');
            }
            return redirect()->route('research.notifications');
        }

        $notifications = DB::table('research_notification')
            ->where('researcher_id', $researcher->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()->toArray();

        // Load preferences
        $preferences = [];
        try {
            $prefRows = DB::table('research_notification_preference')
                ->where('researcher_id', $researcher->id)->get();
            foreach ($prefRows as $p) {
                $preferences[$p->notification_type] = $p;
            }
        } catch (\Exception $e) {}

        return view('research::research.notifications', array_merge(
            $this->getSidebarData('notifications'),
            compact('researcher', 'notifications', 'preferences')
        ));
    }
}
