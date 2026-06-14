<?php

/**
 * ResearchController - Controller for Heratio
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
use AhgResearch\Services\CollaborationService;
use AhgResearch\Services\ValidationQueueService;
use AhgResearch\Services\EntityResolutionService;
use AhgResearch\Services\OdrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchController - Full research portal controller.
 *
 * Migrated from AtoM: ahgResearchPlugin/modules/research/actions/actions.class.php
 * Every action from the AtoM source is preserved here.
 */
class ResearchController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct()
    {
        $this->service = new ResearchService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index()
    {
        return redirect()->route('research.dashboard');
    }

    public function dashboard()
    {
        $stats = $this->service->getDashboardStats();
        $researcher = null;
        $enhancedData = [];
        $unreadNotifications = 0;
        $recentActivity = [];

        if (Auth::check()) {
            $researcher = $this->service->getResearcherByUserId(Auth::id());
            if ($researcher && $researcher->status === 'approved') {
                $enhancedData = $this->service->getEnhancedDashboardData($researcher->id);
                $unreadNotifications = $enhancedData['unread_notifications'] ?? 0;
                $recentActivity = $enhancedData['recent_activity'] ?? [];
            }
        }

        $pendingResearchers = $this->service->getResearchers(['status' => 'pending']);
        $todayBookings = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.booking_date', date('Y-m-d'))
            ->whereIn('b.status', ['pending', 'confirmed'])
            ->select('b.*', 'r.first_name', 'r.last_name', 'rm.name as room_name')
            ->orderBy('b.start_time')->get()->toArray();

        $pendingApprovals = $pendingResearchers;
        $todaySchedule = $todayBookings;
        $recentJournalEntries = $enhancedData['recent_journal_entries'] ?? [];
        $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id());

        return view('research::research.dashboard', array_merge(
            $this->getSidebarData('workspace'),
            compact('stats', 'researcher', 'enhancedData', 'unreadNotifications', 'recentActivity',
                'pendingResearchers', 'pendingApprovals', 'todayBookings', 'todaySchedule',
                'recentJournalEntries', 'isAdmin')
        ));
    }

    // =========================================================================
    // RESEARCHER REGISTRATION
    // =========================================================================
    // register(), registrationComplete(), publicRegister(),
    // storePublicRegistration() and renewal() extracted to
    // ResearchRegistrationController (issue #1269). The unused createAtomUser()
    // compatibility shim (zero callers package-wide) moved with the cluster.
    // Routes re-pointed in packages/ahg-research/routes/web.php
    // (publicRegister + registrationComplete stay PUBLIC; register + renewal
    // stay in the auth group).

    // =========================================================================
    // PROFILE
    // =========================================================================

    public function profile(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $data = [
                'title' => $request->input('title'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'phone' => $request->input('phone'),
                'affiliation_type' => $request->input('affiliation_type'),
                'institution' => $request->input('institution'),
                'department' => $request->input('department'),
                'position' => $request->input('position'),
                'research_interests' => $request->input('research_interests'),
                'current_project' => $request->input('current_project'),
                'orcid_id' => $request->input('orcid_id'),
                'student_id' => $request->input('student_id'),
            ];

            // Self-declared research mode (drives the sidebar + guide). Only
            // accept the three canonical values; anything else is ignored so a
            // tampered POST cannot write a junk level.
            $level = $request->input('experience_level');
            if (in_array($level, ['beginning', 'intermediate', 'advanced'], true)) {
                $data['experience_level'] = $level;
            }

            // Admins can always edit ID fields. Researchers may set each ID
            // field once while it is still empty; once populated it is locked
            // and only an administrator can change it.
            $isAdmin = DB::table('acl_user_group')
                ->where('user_id', Auth::id())->where('group_id', 100)->exists();
            if ($isAdmin || empty($researcher->id_type)) {
                $data['id_type'] = $request->input('id_type');
            }
            if ($isAdmin || empty($researcher->id_number)) {
                $data['id_number'] = $request->input('id_number');
            }

            $this->service->updateResearcher($researcher->id, $data);
            return redirect()->route('research.profile')->with('success', 'Profile updated');
        }

        $recentBookings = $this->service->getResearcherBookings($researcher->id);
        $recentCollections = $this->service->getCollections($researcher->id);
        $recentSavedSearches = $this->service->getSavedSearches($researcher->id);

        return view('research::research.profile', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher', 'recentBookings', 'recentCollections', 'recentSavedSearches')
        ));
    }

    // =========================================================================
    // ADMIN: MANAGE RESEARCHERS (researchers-admin ACL cluster) -> extracted to
    // ResearchAdminController (issue #1269). researchers(), viewResearcher(),
    // approveResearcher(), verifyResearcher(), rejectResearcher(),
    // resetPassword() and suspendResearcher() moved verbatim. These are the
    // core auth/ACL writers (UserProvisioner). Routes repointed in
    // routes/web.php (all seven stay in the admin middleware group).
    // =========================================================================

    // =========================================================================
    // BOOKINGS
    // =========================================================================

    // bookings(), book(), viewBooking(), checkIn() and checkOut() moved to
    // ResearchBookingsController (serial integration, issue #1269). Routes
    // repointed in routes/web.php (admin.bookings + bookings stay admin;
    // book/viewBooking/checkIn/checkOut stay in the auth group).

    // =========================================================================
    // WORKSPACE / TEAM WORKSPACES: workspace(), workspaces() and viewWorkspace()
    // extracted to ResearchWorkspaceController (serial integration, issue #1269).
    // Routes re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // =========================================================================
    // SAVED SEARCHES -> extracted to ResearchSavedSearchesController (issue #1269)
    // (savedSearches, storeSavedSearch, searchSnapshot, searchDiff,
    //  runSavedSearch, destroySavedSearch + the private extractSearchKeyword
    //  helper all moved there)
    // =========================================================================

    // =========================================================================
    // COLLECTIONS (Evidence Sets)
    // =========================================================================

    // collections() and viewCollection() moved to ResearchCollectionsController
    // (serial integration, issue #1269). Routes repointed in routes/web.php.

    // =========================================================================
    // ANNOTATIONS
    // =========================================================================

    // annotations() moved to ResearchAnnotationsController (stage 2, issue #1253).

    // =========================================================================
    // CITATIONS
    // =========================================================================

    // cite() and citeExport() moved to ResearchCitationsController
    // (stage 3 Part B, issue #1253). Both remain PUBLIC routes.

    // =========================================================================
    // PROJECTS
    // =========================================================================
    // projects() + viewProject() moved to ResearchProjectsController
    // (project-subsystem stage, issue #1269). createProject()/storeProject()
    // moved likewise (see breadcrumb below). Routes re-pointed in routes/web.php.

    // =========================================================================
    // JOURNAL (personal diary): journal() and journalEntry() extracted to
    // ResearchJournalController (serial integration, issue #1269), alongside
    // createJournalEntry()/showJournalEntry(). Routes re-pointed in routes/web.php.
    // =========================================================================

    // =========================================================================
    // SOURCE ASSESSMENTS
    // =========================================================================
    // assessments() extracted to ResearchAssessmentsController (issue #1269).
    // Route re-pointed in routes/web.php (stays in the auth group).

    // =========================================================================
    // BIBLIOGRAPHIES
    // =========================================================================
    // Extracted to ResearchBibliographiesController (stage 6, issue #1253 /
    // #1269): bibliographies(), viewBibliography(), exportBibliography(),
    // exportBibliographyEntry(). Routes re-pointed in
    // packages/ahg-research/routes/web.php with names + URIs + middleware groups
    // unchanged (all four stay in the auth group). Verbatim lift - the methods
    // used only the ResearchControllerHelpers trait (getSidebarData) and the
    // injected ResearchService, plus the CitationExportService resolved from the
    // container; no cross-calls to other ResearchController methods.
    // =========================================================================
    // REPORTS
    // =========================================================================
    // Extracted to ResearchReportsController (stage 5, issue #1253 / #1269):
    // reports(), reportTemplates(), viewReport(). Routes re-pointed in
    // packages/ahg-research/routes/web.php. Verbatim lift - the methods used
    // only the ResearchControllerHelpers trait and the injected ResearchService,
    // with no cross-calls to other ResearchController methods.
    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================
    // Extracted to ResearchNotificationsController (stage 8, issue #1269):
    // notifications(). Route re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // =========================================================================
    // ADMIN: READING ROOMS: rooms() and editRoom() extracted to
    // ResearchRoomsController (serial integration, issue #1269). Routes
    // re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // =========================================================================
    // ADMIN: SEATS, EQUIPMENT, RETRIEVAL QUEUE, WALK-IN
    // seats() extracted to ResearchSeatsController; equipment() and
    // equipmentHistory() extracted to ResearchEquipmentController (serial
    // integration, issue #1269). Routes re-pointed in routes/web.php.
    // =========================================================================

    // retrievalQueue() extracted to ResearchRetrievalQueueController (issue #1269). Route re-pointed in routes/web.php.

    // walkIn() extracted to ResearchWalkInsController (issue #1269). Route re-pointed in routes/web.php.

    // =========================================================================
    // ADMIN: RESEARCHER TYPES, STATISTICS, INSTITUTIONS
    // =========================================================================
    // adminTypes(), adminStatistics() and institutions() extracted to
    // ResearchAdminReferenceController (issue #1269). Routes re-pointed in
    // routes/web.php (all stay in the admin middleware group; adminStatistics
    // keeps BOTH /admin/statistics and /adminStatistics routes). activities()
    // stays here (later stage).

    // =========================================================================
    // ADMIN: ACTIVITIES (activities() stays here; later stage)
    // =========================================================================

    public function activities(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        $typeFilter = $request->input('type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Combine all activity sources into a unified log
        $activities = collect();

        // 1. Activity log entries
        $logQuery = DB::table('research_activity_log as al')
            ->leftJoin('research_researcher as r', 'al.researcher_id', '=', 'r.id')
            ->select(
                'al.id', 'al.activity_type as type', 'al.entity_title as title',
                'al.entity_type', 'al.entity_id', 'al.details',
                'al.created_at', 'al.ip_address',
                DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"),
                DB::raw("'log' as source")
            );
        if ($typeFilter) $logQuery->where('al.activity_type', $typeFilter);
        if ($dateFrom) $logQuery->where('al.created_at', '>=', $dateFrom);
        if ($dateTo) $logQuery->where('al.created_at', '<=', $dateTo . ' 23:59:59');
        $activities = $activities->merge($logQuery->orderByDesc('al.created_at')->limit(100)->get());

        // 2. Bookings as activities
        $bookingQuery = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->select(
                'b.id', DB::raw("'booking' as type"),
                DB::raw("CONCAT('Booking: ', rm.name, ' (', b.status, ')') as title"),
                DB::raw("'booking' as entity_type"), 'b.id as entity_id',
                'b.purpose as details', 'b.created_at', DB::raw("NULL as ip_address"),
                DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"),
                DB::raw("'booking' as source")
            );
        if ($typeFilter && $typeFilter !== 'booking') $bookingQuery->whereRaw('0=1');
        if ($dateFrom) $bookingQuery->where('b.booking_date', '>=', $dateFrom);
        if ($dateTo) $bookingQuery->where('b.booking_date', '<=', $dateTo);
        $activities = $activities->merge($bookingQuery->orderByDesc('b.created_at')->limit(50)->get());

        // 3. Walk-in visits
        $walkInQuery = DB::table('research_walk_in_visitor as w')
            ->leftJoin('research_reading_room as rm', 'w.reading_room_id', '=', 'rm.id')
            ->select(
                'w.id', DB::raw("'walk_in' as type"),
                DB::raw("CONCAT('Walk-in: ', w.first_name, ' ', w.last_name) as title"),
                DB::raw("'walk_in' as entity_type"), 'w.id as entity_id',
                'w.purpose as details', 'w.created_at', DB::raw("NULL as ip_address"),
                DB::raw("CONCAT(w.first_name, ' ', w.last_name) as researcher_name"),
                DB::raw("'walk_in' as source")
            );
        if ($typeFilter && $typeFilter !== 'walk_in') $walkInQuery->whereRaw('0=1');
        if ($dateFrom) $walkInQuery->where('w.visit_date', '>=', $dateFrom);
        if ($dateTo) $walkInQuery->where('w.visit_date', '<=', $dateTo);
        $activities = $activities->merge($walkInQuery->orderByDesc('w.created_at')->limit(50)->get());

        // 4. Scheduled events
        $eventQuery = DB::table('research_activity as a')
            ->leftJoin('research_reading_room as rm', 'a.reading_room_id', '=', 'rm.id')
            ->select(
                'a.id', 'a.activity_type as type', 'a.title',
                DB::raw("'event' as entity_type"), 'a.id as entity_id',
                'a.description as details', 'a.created_at', DB::raw("NULL as ip_address"),
                'a.organizer_name as researcher_name',
                DB::raw("'event' as source")
            );
        if ($typeFilter && !in_array($typeFilter, ['workshop', 'exhibition', 'lecture', 'seminar', 'tour'])) $eventQuery->whereRaw('0=1');
        elseif ($typeFilter) $eventQuery->where('a.activity_type', $typeFilter);
        if ($dateFrom) $eventQuery->where('a.start_date', '>=', $dateFrom);
        if ($dateTo) $eventQuery->where('a.start_date', '<=', $dateTo);
        $activities = $activities->merge($eventQuery->orderByDesc('a.created_at')->limit(50)->get());

        // Sort combined by date desc and limit
        $activities = $activities->sortByDesc('created_at')->take(100)->values()->toArray();

        // Get distinct types for filter
        $activityTypes = collect($activities)->pluck('type')->unique()->sort()->values()->toArray();

        return view('research::research.activities', array_merge(
            $this->getSidebarData('activities'),
            compact('activities', 'activityTypes', 'typeFilter', 'dateFrom', 'dateTo')
        ));
    }

    // =========================================================================
    // EVIDENCE VIEWER + AJAX SEARCH ITEMS: evidenceViewer() and searchItems()
    // extracted to ResearchEvidenceController (serial integration, issue #1269).
    // Routes re-pointed in routes/web.php (both stay in the auth group).
    // =========================================================================

    // =========================================================================
    // AJAX: ADD TO COLLECTION
    // =========================================================================

    // addToCollection() and createCollectionAjax() moved to
    // ResearchCollectionsController (serial integration, issue #1269).
    // Routes repointed in routes/web.php.

    // =========================================================================
    // API KEYS
    // =========================================================================
    // apiKeys() extracted to ResearchApiKeysController (issue #1269). Route
    // re-pointed in routes/web.php (stays in the auth group).

    // =========================================================================
    // RENEWAL
    // =========================================================================

    // =========================================================================
    // DEDICATED ROUTE METHODS (Collections CRUD)
    // =========================================================================

    // createCollection(), storeCollection(), updateCollection(),
    // destroyCollection(), addItemToCollection() and removeItemFromCollection()
    // moved to ResearchCollectionsController (serial integration, issue #1269).
    // Routes repointed in routes/web.php. (getCollections() remains a SERVICE
    // method, still called from this controller's workspace()/etc.)

    // =========================================================================
    // DEDICATED ROUTE METHODS (Bookings)
    // =========================================================================

    // confirmBooking(), checkInBooking(), checkOutBooking(), noShowBooking()
    // and cancelBooking() moved to ResearchBookingsController (serial
    // integration, issue #1269). Routes repointed in routes/web.php.

    // =========================================================================
    // DEDICATED ROUTE METHODS (Journal): createJournalEntry() and
    // showJournalEntry() extracted to ResearchJournalController (serial
    // integration, issue #1269). Routes re-pointed in routes/web.php.
    // =========================================================================

    // =========================================================================
    // DEDICATED ROUTE METHODS (Projects)
    // =========================================================================

    // createProject() / storeProject() moved to ResearchProjectsController
    // (project-subsystem stage, issue #1269). Routes re-pointed in routes/web.php.

    // storeAnnotation() / updateAnnotation() / destroyAnnotation() moved to
    // ResearchAnnotationsController (stage 2, issue #1253).

    // =========================================================================
    // DEDICATED ROUTE METHODS (Researchers Admin) -> resetPassword() and
    // suspendResearcher() moved verbatim to ResearchAdminController with the
    // rest of the researchers-admin ACL cluster (issue #1269). Routes
    // repointed in routes/web.php.
    // =========================================================================

    // =========================================================================
    // DEDICATED ROUTE METHODS (Saved Searches) -> extracted to
    // ResearchSavedSearchesController (issue #1269)
    // =========================================================================

    // =========================================================================
    // DEDICATED ROUTE METHODS (Public Registration) + RENEWAL
    // =========================================================================
    // storePublicRegistration() and renewal() extracted to
    // ResearchRegistrationController (issue #1269), alongside the rest of the
    // registration cluster. Routes re-pointed in routes/web.php.

    // =========================================================================
    // FINDING AID EXPORT
    // =========================================================================
    // exportNotes(), exportFindingAid(), generateFindingAid() and the private
    // helper getCollectionFindingAidData() extracted to ResearchExportsController
    // (serial integration, issue #1269). Routes re-pointed in routes/web.php.

    // =========================================================================
    // TEAM WORKSPACES: workspaces() and viewWorkspace() extracted to
    // ResearchWorkspaceController (serial integration, issue #1269). Routes
    // re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // Validation Queue, Entity Resolution and ODRL Policies extracted to
    // ResearchValidationQueueController, ResearchEntityResolutionController and
    // ResearchOdrlPoliciesController respectively (stage 7, issue #1269). The
    // researcher/target autocomplete + resolveTargetName helper moved with ODRL.

    // =========================================================================
    // DOCUMENT TEMPLATES
    // =========================================================================

    // documentTemplates() extracted to ResearchDocumentTemplatesController (issue #1269). Routes re-pointed in routes/web.php.

    // =========================================================================
    // PROJECT ANALYSIS TOOLS
    // =========================================================================

    // loadProjectContext() helper + the full project analysis / visualization /
    // research-output cluster moved to the project-subsystem controllers
    // (issue #1269): knowledgeGraph(), assertions(), hypotheses(),
    // extractionJobs(), snapshots(), viewSnapshot(), assertionBatchReview()
    // [analysis]; timelineBuilder(), mapBuilder(), networkGraph()
    // [visualization]; roCrate(), reproducibilityPack(), mintDoi(),
    // ethicsMilestones(), complianceDashboard() [research output]. ALL callers
    // of loadProjectContext() moved with them, so the helper is gone from here;
    // ResearchProjectsController + ResearchProjectOutputsController each carry
    // their own verbatim copy. Routes re-pointed in routes/web.php.

    // =========================================================================
    // COLLABORATOR MANAGEMENT
    // =========================================================================
    // Extracted to ResearchCollaborationController (stage 8, issue #1269):
    // inviteCollaborator(), shareProject(), projectCollaborators(). Routes
    // re-pointed in packages/ahg-research/routes/web.php. The shared private
    // helper loadProjectContext() has since moved out with the project subsystem
    // (issue #1269); ResearchCollaborationController already carries its own
    // verbatim copy.
    // =========================================================================

    // =========================================================================
    // STUDIO (NotebookLM-style artefact generator)
    // =========================================================================
    // Extracted to ResearchStudioController (stage 8, issue #1269): studio(),
    // studioGenerate(), studioShow(), studioDownload(), studioDelete(). Routes
    // re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // =========================================================================
    // NOTEBOOKS (researcher-private scratchpad)
    // -------------------------------------------------------------------------
    // Extracted to AhgResearch\Controllers\ResearchNotebooksController
    // (stage 4 of the monolith decomposition, issue #1253 / #1269).
    // =========================================================================

    // =========================================================================
    // CROSS-FONDS QUERY
    // =========================================================================
    // crossFondsQuery() extracted to ResearchAnalyticsController (serial
    // integration, issue #1269). Route re-pointed in routes/web.php.

    // =========================================================================
    // MOBILE / PWA + OFFLINE SYNC
    // =========================================================================
    // mobileHome() and offlineSync() extracted to ResearchMobileController
    // (serial integration, issue #1269). Routes re-pointed in routes/web.php.

    // =========================================================================
    // ORCID INTEGRATION
    // =========================================================================

    // orcidLink(), orcidSaveCredentials(), orcidClearCredentials(),
    // orcidAuthorize(), orcidCallback(), orcidSync(), orcidUnlink(),
    // orcidFetchPublic() and orcidPullProfile() moved to ResearchOrcidController
    // (serial integration, issue #1269). Routes repointed in routes/web.php
    // (orcidFetchPublic stays PUBLIC; the rest stay in the auth group).

    // =========================================================================
    // REAL-TIME COLLABORATION (polling fallback)
    // =========================================================================
    // Extracted to ResearchCollaborationController (stage 8, issue #1269):
    // collabJoin(), collabPoll(), collabComment(), collabCommentResolve(),
    // collabPanel(). Routes re-pointed in packages/ahg-research/routes/web.php.
    // =========================================================================

    // =========================================================================
    // ANALYTICS DASHBOARD
    // =========================================================================
    // analytics() extracted to ResearchAnalyticsController (serial integration,
    // issue #1269). Route re-pointed in routes/web.php.
}
