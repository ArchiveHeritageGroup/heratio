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

    public function register(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in to register');
        }

        $userId = Auth::id();
        $existing = $this->service->getResearcherByUserId($userId);
        $existingResearcher = null;

        if ($existing) {
            if ($existing->status === 'rejected') {
                $existingResearcher = $existing;
            } else {
                return redirect()->route('research.profile');
            }
        }

        $user = DB::table('user')->where('id', $userId)->first();

        if ($request->isMethod('post')) {
            try {
                $data = [
                    'user_id' => $userId,
                    'title' => $request->input('title'),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'phone' => $request->input('phone'),
                    'affiliation_type' => $request->input('affiliation_type'),
                    'institution' => $request->input('institution'),
                    'department' => $request->input('department'),
                    'position' => $request->input('position'),
                    'research_interests' => $request->input('research_interests'),
                    'current_project' => $request->input('current_project'),
                    'orcid_id' => $request->input('orcid_id'),
                    'id_type' => $request->input('id_type'),
                    'id_number' => $request->input('id_number'),
                    'student_id' => $request->input('student_id'),
                ];

                if ($existingResearcher) {
                    $data['status'] = 'pending';
                    $data['rejection_reason'] = null;
                    DB::table('research_researcher')
                        ->where('id', $existingResearcher->id)
                        ->update($data);
                    return redirect()->route('research.registrationComplete')
                        ->with('success', 'Re-registration submitted for review');
                } else {
                    $this->service->registerResearcher($data);
                    return redirect()->route('research.registrationComplete')
                        ->with('success', 'Registration submitted');
                }
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return view('research::research.register', array_merge(
            $this->getSidebarData('profile'),
            compact('user', 'existingResearcher')
        ));
    }

    public function registrationComplete()
    {
        return view('research::research.registration-complete', $this->getSidebarData('profile'));
    }

    public function publicRegister(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('researcher.register');
        }

        if ($request->isMethod('post')) {
            $email = trim($request->input('email'));
            $username = trim($request->input('username'));
            $password = $request->input('password');
            $confirmPassword = $request->input('confirm_password');

            $errors = [];
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email address is required';
            }
            if (empty($username) || strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters';
            }
            if (empty($password) || strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            }

            $existingUser = DB::table('user')->where('email', $email)->first();
            $existingByUsername = DB::table('user')->where('username', $username)->first();

            if ($existingUser) {
                if ($existingUser->active) {
                    $errors[] = 'Email address is already registered';
                } else {
                    $wasRejected = DB::table('research_researcher_audit')
                        ->where('user_id', $existingUser->id)
                        ->where('status', 'rejected')
                        ->exists();
                    if (!$wasRejected) {
                        $errors[] = 'This account has been disabled. Please contact the administrator.';
                    }
                }
            }
            if ($existingByUsername && $existingByUsername->active && (!$existingUser || $existingByUsername->id != $existingUser->id)) {
                $errors[] = 'Username is already taken';
            }

            if (!empty($errors)) {
                return back()->with('error', implode('<br>', $errors));
            }

            try {
                DB::beginTransaction();

                $wasRejected = $existingUser && !$existingUser->active &&
                    DB::table('research_researcher_audit')
                        ->where('user_id', $existingUser->id)
                        ->where('status', 'rejected')
                        ->exists();

                if ($wasRejected) {
                    // Re-activation of a previously rejected account: route all
                    // core-user writes through the provisioner (no raw hashing here).
                    $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);
                    $provisioner->setPassword($existingUser->id, $password);
                    $provisioner->updateUser($existingUser->id, [
                        'username' => $username,
                        'active' => 0,
                    ]);
                    $userId = $existingUser->id;
                } else {
                    // Use the provisioner contract to create users so the research
                    // package does not write directly to core auth tables.
                    $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);
                    $userId = $provisioner->createUser($username, $email, $password);
                }

                // Add to the 'researcher' seat (group id 99) using the provisioner
                $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);
                $provisioner->addToGroup($userId, 99);

                $this->service->registerResearcher([
                    'user_id' => $userId,
                    'title' => $request->input('title'),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $email,
                    'phone' => $request->input('phone'),
                    'affiliation_type' => $request->input('affiliation_type', 'independent'),
                    'institution' => $request->input('institution'),
                    'department' => $request->input('department'),
                    'position' => $request->input('position'),
                    'research_interests' => $request->input('research_interests'),
                    'current_project' => $request->input('current_project'),
                    'orcid_id' => $request->input('orcid_id'),
                    'id_type' => $request->input('id_type'),
                    'id_number' => $request->input('id_number'),
                    'student_id' => $request->input('student_id'),
                ]);
                DB::commit();

                return redirect()->route('research.registrationComplete')
                    ->with('success', 'Registration successful! Pending approval.');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Registration failed: ' . $e->getMessage());
            }
        }

        return view('research::research.public-register', $this->getSidebarData(''));
    }

    protected function createAtomUser(string $username, string $email, string $password): int
    {
        // Delegates to the central provisioner so all core-user creation goes
        // through one place (this method is retained for backward compatibility).
        return app(\AhgResearch\Contracts\UserProvisionerInterface::class)
            ->createUser($username, $email, $password);
    }

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
    // ADMIN: MANAGE RESEARCHERS
    // =========================================================================

    public function researchers(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $filter = $request->input('filter', 'all');
        $query = $request->input('q');

        $statusFilter = ($filter !== 'all') ? $filter : null;
        $researchers = $this->service->getResearchers([
            'status' => $statusFilter,
            'search' => $query,
        ]);

        $counts = [
            'all' => DB::table('research_researcher')->count(),
            'pending' => DB::table('research_researcher')->where('status', 'pending')->count(),
            'approved' => DB::table('research_researcher')->where('status', 'approved')->count(),
            'suspended' => DB::table('research_researcher')->where('status', 'suspended')->count(),
            'expired' => DB::table('research_researcher')->where('status', 'expired')->count(),
            'rejected' => DB::table('research_researcher')->where('status', 'rejected')->count(),
        ];

        return view('research::research.researchers', array_merge(
            $this->getSidebarData('researchers'),
            compact('researchers', 'filter', 'counts', 'query')
        ));
    }

    public function viewResearcher(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404, 'Not found');

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);
            if ($action === 'approve') {
                $this->service->approveResearcher($id, Auth::id());
                $provisioner->updateUser($researcher->user_id, ['active' => 1]);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Approved');
            } elseif ($action === 'suspend') {
                DB::table('research_researcher')->where('id', $id)->update(['status' => 'suspended']);
                // Also deactivate the linked account, consistent with suspendResearcher().
                $provisioner->deactivateUser($researcher->user_id);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Suspended');
            }
        }

        // #74 encryption_field_donor_information / personal_notes: decrypt
        // PII columns before passing to the view. Idempotent for plaintext
        // values (EncryptionService::decrypt round-trips when isCiphertext
        // is false), so the call is safe whether the operator has the
        // category on or off.
        $enc = new \AhgCore\Services\EncryptionService();
        $researcher->phone = $enc->decrypt(
            \AhgCore\Services\EncryptionService::CATEGORY_DONOR_INFORMATION,
            (string) ($researcher->phone ?? ''),
            'research_researcher', 'phone', $researcher->id
        );
        $researcher->id_number = $enc->decrypt(
            \AhgCore\Services\EncryptionService::CATEGORY_DONOR_INFORMATION,
            (string) ($researcher->id_number ?? ''),
            'research_researcher', 'id_number', $researcher->id
        );
        if (property_exists($researcher, 'notes')) {
            $researcher->notes = $enc->decrypt(
                \AhgCore\Services\EncryptionService::CATEGORY_PERSONAL_NOTES,
                (string) ($researcher->notes ?? ''),
                'research_researcher', 'notes', $researcher->id
            );
        }

        $bookings = $this->service->getResearcherBookings($id);

        return view('research::research.view-researcher', array_merge(
            $this->getSidebarData('researchers'),
            compact('researcher', 'bookings')
        ));
    }

    public function approveResearcher(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $this->service->approveResearcher($id, Auth::id());
        app(\AhgResearch\Contracts\UserProvisionerInterface::class)->updateUser($researcher->user_id, ['active' => 1]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher approved and account activated');
    }

    /**
     * Manually flip a researcher's verified flag.
     *
     * The registration flow expects the researcher to confirm by email; when
     * mail delivery is down they never receive it and instead confirm their
     * identity by phone. This lets an admin set the verified flag directly,
     * reusing the existing id_verified column + its audit fields (by / at).
     * POST `verified` = '1' to verify, '0' to clear.
     */
    public function verifyResearcher(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $verified = (string) $request->input('verified', '1') === '1' ? 1 : 0;
        DB::table('research_researcher')->where('id', $id)->update([
            'id_verified'    => $verified,
            'id_verified_by' => $verified ? Auth::id() : null,
            'id_verified_at' => $verified ? now() : null,
            'updated_at'     => now(),
        ]);

        return redirect()->back()->with('success', $verified
            ? 'Researcher marked as verified'
            : 'Researcher verification removed');
    }

    public function rejectResearcher(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $reason = $request->input('reason', '');

        DB::table('research_researcher_audit')->insert([
            'original_id' => $researcher->id,
            'user_id' => $researcher->user_id,
            'title' => $researcher->title,
            'first_name' => $researcher->first_name,
            'last_name' => $researcher->last_name,
            'email' => $researcher->email,
            'phone' => $researcher->phone,
            'affiliation_type' => $researcher->affiliation_type,
            'institution' => $researcher->institution,
            'department' => $researcher->department,
            'position' => $researcher->position,
            'research_interests' => $researcher->research_interests,
            'current_project' => $researcher->current_project,
            'orcid_id' => $researcher->orcid_id,
            'id_type' => $researcher->id_type,
            'id_number' => $researcher->id_number,
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'archived_by' => Auth::id(),
            'archived_at' => date('Y-m-d H:i:s'),
            'original_created_at' => $researcher->created_at,
            'original_updated_at' => $researcher->updated_at,
        ]);

        DB::table('research_researcher')->where('id', $id)->delete();
        app(\AhgResearch\Contracts\UserProvisionerInterface::class)->deactivateUser($researcher->user_id);

        return redirect()->route('research.researchers')
            ->with('success', 'Researcher registration rejected and archived');
    }

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
    // JOURNAL
    // =========================================================================

    public function journal(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $filters = [
            'project_id' => $request->input('project_id'),
            'entry_type' => $request->input('entry_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('q'),
        ];

        $query = DB::table('research_journal_entry')
            ->where('researcher_id', $researcher->id);

        if ($filters['project_id']) $query->where('project_id', $filters['project_id']);
        if ($filters['entry_type']) $query->where('entry_type', $filters['entry_type']);
        if ($filters['date_from']) $query->where('entry_date', '>=', $filters['date_from']);
        if ($filters['date_to']) $query->where('entry_date', '<=', $filters['date_to']);
        if ($filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('content', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $entries = $query->orderBy('entry_date', 'desc')->orderBy('created_at', 'desc')->get()->toArray();

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')
            ->orderBy('p.title')->get()->toArray();

        if ($request->isMethod('post') && $request->input('do') === 'create') {
            $content = $this->service->sanitizeHtml($request->input('content', ''));
            if ($content) {
                DB::table('research_journal_entry')->insert([
                    'researcher_id' => $researcher->id,
                    'title' => $request->input('title'),
                    'content' => $content,
                    'content_format' => 'html',
                    'project_id' => $request->input('project_id') ?: null,
                    'entry_type' => $request->input('entry_type') ?: 'manual',
                    'time_spent_minutes' => $request->input('time_spent_minutes') ?: null,
                    'tags' => $request->input('tags'),
                    'entry_date' => $request->input('entry_date') ?: date('Y-m-d'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect()->route('research.journal')->with('success', 'Journal entry created');
            }
        }

        $journals = DB::table('research_journal')
            ->where('researcher_id', $researcher->id)
            ->orderByDesc('updated_at')->get()->toArray();

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters', 'journals')
        ));
    }

    public function journalEntry(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $entry = DB::table('research_journal_entry')->where('id', $id)->first();
        if (!$entry || $entry->researcher_id != $researcher->id) abort(404);

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')->orderBy('p.title')->get()->toArray();

        if ($request->isMethod('post')) {
            if ($request->input('form_action') === 'delete') {
                DB::table('research_journal_entry')
                    ->where('id', $id)
                    ->where('researcher_id', $researcher->id)
                    ->delete();
                return redirect()->route('research.journal')->with('success', 'Entry deleted');
            }
            $content = $this->service->sanitizeHtml($request->input('content', ''));
            DB::table('research_journal_entry')->where('id', $id)->where('researcher_id', $researcher->id)->update([
                'title' => $request->input('title'),
                'content' => $content,
                'content_format' => 'html',
                'project_id' => $request->input('project_id') ?: null,
                'entry_type' => $request->input('entry_type', $entry->entry_type),
                'time_spent_minutes' => $request->input('time_spent_minutes') ?: null,
                'tags' => $request->input('tags'),
                'is_private' => $request->has('is_private') ? 1 : 0,
                'entry_date' => $request->input('entry_date') ?: $entry->entry_date,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.journalEntry', $id)->with('success', 'Entry updated');
        }

        return view('research::research.journal-entry', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entry', 'projects')
        ));
    }

    // =========================================================================
    // BIBLIOGRAPHIES
    // =========================================================================

    public function assessments(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $culture = app()->getLocale();

        $assessments = DB::table('research_source_assessment as sa')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('sa.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'sa.object_id', '=', 's.object_id')
            ->leftJoin('research_researcher as r', 'sa.researcher_id', '=', 'r.id')
            ->select('sa.*', 'ioi.title as object_title', 's.slug as object_slug',
                DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"))
            ->orderByDesc('sa.assessed_at')
            ->limit(100)
            ->get()->toArray();

        return view('research::research.assessments', array_merge(
            $this->getSidebarData('assessments'),
            compact('assessments')
        ));
    }

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
    // ADMIN: RESEARCHER TYPES
    // =========================================================================

    public function adminTypes(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code') ?: \Illuminate\Support\Str::slug($request->input('name'), '_'),
                'description' => $request->input('description') ?: null,
                'max_booking_days_advance' => (int) ($request->input('max_booking_days_advance', 14)),
                'max_booking_hours_per_day' => (int) ($request->input('max_booking_hours_per_day', 4)),
                'max_materials_per_booking' => (int) ($request->input('max_materials_per_booking', 10)),
                'can_remote_access' => $request->has('can_remote_access') ? 1 : 0,
                'can_request_reproductions' => $request->has('can_request_reproductions') ? 1 : 0,
                'can_export_data' => $request->has('can_export_data') ? 1 : 0,
                'requires_id_verification' => $request->has('requires_id_verification') ? 1 : 0,
                'auto_approve' => $request->has('auto_approve') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'expiry_months' => (int) ($request->input('expiry_months', 12)),
                'priority_level' => (int) ($request->input('priority_level', 5)),
                'sort_order' => (int) ($request->input('sort_order', 100)),
            ];

            if ($action === 'create') {
                $data['created_at'] = now();
                DB::table('research_researcher_type')->insert($data);
                return redirect()->route('research.adminTypes')->with('success', 'Type created.');
            }

            if ($action === 'update') {
                $data['updated_at'] = now();
                DB::table('research_researcher_type')->where('id', (int) $request->input('type_id'))->update($data);
                return redirect()->route('research.adminTypes')->with('success', 'Type updated.');
            }

            if ($action === 'delete') {
                DB::table('research_researcher_type')->where('id', (int) $request->input('type_id'))->delete();
                return redirect()->route('research.adminTypes')->with('success', 'Type deleted.');
            }
        }

        $types = $this->service->getResearcherTypes();
        return view('research::research.admin-types', array_merge(
            $this->getSidebarData('adminTypes'),
            compact('types')
        ));
    }

    // =========================================================================
    // ADMIN: STATISTICS
    // =========================================================================

    public function adminStatistics(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        $stats = [
            'total_researchers' => DB::table('research_researcher')->count(),
            'approved_researchers' => DB::table('research_researcher')->where('status', 'approved')->count(),
            'total_bookings' => DB::table('research_booking')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'completed_bookings' => DB::table('research_booking')
                ->where('status', 'completed')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'total_collections' => DB::table('research_collection')->count(),
            'total_collection_items' => DB::table('research_collection_item')->count(),
            'total_annotations' => DB::table('research_annotation')->count(),
            'total_projects' => DB::table('research_project')->count(),
            'active_projects' => DB::table('research_project')->where('status', 'active')->count(),
            'new_projects_period' => DB::table('research_project')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
            'no_show_bookings' => DB::table('research_booking')
                ->where('status', 'no_show')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'bookings_this_week' => DB::table('research_booking')
                ->whereBetween('booking_date', [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))])->count(),
            'materials_requested' => DB::table('research_material_request')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
            'materials_in_use' => DB::table('research_material_request')
                ->where('status', 'in_use')->count(),
            'total_citations' => DB::table('research_citation_log')->count(),
            'total_views' => DB::table('research_activity_log')
                ->where('activity_type', 'view')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
        ];

        // By type breakdown
        try {
            $stats['by_type'] = DB::table('research_researcher as r')
                ->leftJoin('research_researcher_type as t', 'r.researcher_type_id', '=', 't.id')
                ->select(DB::raw('COALESCE(t.name, "Unspecified") as name'), DB::raw('COUNT(*) as count'))
                ->groupBy('t.name')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['by_type'] = []; }

        try {
            $stats['projects_by_status'] = DB::table('research_project')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['projects_by_status'] = []; }

        try {
            $stats['reproductions_by_status'] = DB::table('research_reproduction_request')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['reproductions_by_status'] = []; }

        // Chart data: registrations over time (monthly)
        $regData = [];
        try {
            $regData = DB::table('research_researcher')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as count")
                ->where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo . ' 23:59:59')
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->orderBy('period')->get()->toArray();
        } catch (\Exception $e) {}

        // Chart data: bookings by room
        $roomData = [];
        try {
            $roomData = DB::table('research_booking as b')
                ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
                ->whereBetween('b.booking_date', [$dateFrom, $dateTo])
                ->select('rm.name as room_name', DB::raw('COUNT(*) as count'))
                ->groupBy('rm.name')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) {}

        // Most active researchers (cloned from PSIS adminStatistics — includes view_count + citation_count)
        $activeResearchers = [];
        try {
            $activeResearchers = DB::table('research_researcher as r')
                ->select('r.id', 'r.first_name', 'r.last_name', 'r.institution',
                    DB::raw('(SELECT COUNT(*) FROM research_booking WHERE researcher_id = r.id) as booking_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_collection WHERE researcher_id = r.id) as collection_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_activity_log WHERE user_id = r.user_id AND activity_type = "view") as view_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_citation_log WHERE researcher_id = r.id) as citation_count'))
                ->where('r.status', 'approved')
                ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM research_booking WHERE researcher_id = r.id)'))
                ->limit(10)->get()->toArray();
        } catch (\Exception $e) {}

        // Most viewed items (cloned from PSIS adminStatistics)
        $mostViewed = [];
        try {
            $mostViewed = DB::table('research_activity_log as a')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('ioi.id', '=', 'a.object_id')
                        ->where('ioi.culture', '=', app()->getLocale());
                })
                ->where('a.activity_type', 'view')
                ->whereBetween('a.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select('ioi.title', DB::raw('COUNT(*) as view_count'))
                ->groupBy('a.object_id', 'ioi.title')
                ->orderByDesc('view_count')
                ->limit(10)
                ->get()->toArray();
        } catch (\Exception $e) {}

        // Most cited items (cloned from PSIS adminStatistics)
        $mostCited = [];
        try {
            $mostCited = DB::table('research_citation_log as c')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('ioi.id', '=', 'c.object_id')
                        ->where('ioi.culture', '=', app()->getLocale());
                })
                ->whereBetween('c.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select('ioi.title', DB::raw('COUNT(*) as citation_count'))
                ->groupBy('c.object_id', 'ioi.title')
                ->orderByDesc('citation_count')
                ->limit(10)
                ->get()->toArray();
        } catch (\Exception $e) {}

        return view('research::research.admin-statistics', array_merge(
            $this->getSidebarData('adminStatistics'),
            compact('stats', 'dateFrom', 'dateTo', 'regData', 'roomData', 'activeResearchers', 'mostViewed', 'mostCited')
        ));
    }

    // =========================================================================
    // ADMIN: INSTITUTIONS & ACTIVITIES
    // =========================================================================

    public function institutions(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code') ?: \Illuminate\Support\Str::slug($request->input('name'), '_'),
                'description' => $request->input('description') ?: null,
                'url' => $request->input('url') ?: null,
                'contact_name' => $request->input('contact_name') ?: null,
                'contact_email' => $request->input('contact_email') ?: null,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ];

            if ($action === 'create') {
                $data['created_at'] = now();
                DB::table('research_institution')->insert($data);
                return redirect()->route('research.institutions')->with('success', 'Institution added.');
            }
            if ($action === 'update') {
                $data['updated_at'] = now();
                DB::table('research_institution')->where('id', (int) $request->input('institution_id'))->update($data);
                return redirect()->route('research.institutions')->with('success', 'Institution updated.');
            }
            if ($action === 'delete') {
                DB::table('research_institution')->where('id', (int) $request->input('institution_id'))->delete();
                return redirect()->route('research.institutions')->with('success', 'Institution deleted.');
            }
        }

        $institutions = DB::table('research_institution')->orderBy('name')->get()->toArray();
        return view('research::research.institutions', array_merge(
            $this->getSidebarData('institutions'),
            compact('institutions')
        ));
    }

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
    // EVIDENCE VIEWER
    // =========================================================================

    /**
     * Evidence viewer for an archival object.
     * Migrated from ahgResearchPlugin evidence action.
     */
    public function evidenceViewer(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $culture = app()->getLocale();

        // Get object info
        $source = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        if (!$source) {
            abort(404);
        }

        // Get source assessment for type info
        $assessment = DB::table('research_source_assessment')
            ->where('object_id', $objectId)
            ->orderByDesc('assessed_at')
            ->first();

        $source->source_type = $assessment->source_type ?? null;
        $source->date = $assessment->assessed_at ?? null;

        // Get repository name
        $repo = DB::table('information_object as io2')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'io2.repository_id')->where('ai.culture', $culture);
            })
            ->where('io2.id', $objectId)
            ->select('ai.authorized_form_of_name as name')
            ->first();
        $source->repository = $repo->name ?? null;

        // Get digital object image
        $imageUrl = null;
        $do = DB::table('digital_object')->where('object_id', $objectId)->orderBy('usage_id')->first();
        if ($do) {
            if (str_starts_with($do->path ?? '', 'http')) {
                $imageUrl = $do->path;
            } else {
                $ref = DB::table('digital_object')->where('parent_id', $do->id)->where('usage_id', 141)->first();
                if ($ref) {
                    $imageUrl = rtrim($ref->path, '/') . '/' . $ref->name;
                } elseif (str_starts_with($do->mime_type ?? '', 'image/')) {
                    $imageUrl = rtrim($do->path, '/') . '/' . $do->name;
                }
            }
        }

        // Get annotations as notes
        $notes = '';
        $researcher = DB::table('research_researcher')->where('user_id', auth()->id())->first();
        if ($researcher) {
            $ann = DB::table('research_annotation')
                ->where('object_id', $objectId)
                ->where('researcher_id', $researcher->id)
                ->orderByDesc('created_at')
                ->first();
            $notes = $ann->content ?? '';
        }

        // Save notes via POST
        if ($request->isMethod('post') && $researcher) {
            DB::table('research_annotation')->updateOrInsert(
                ['object_id' => $objectId, 'researcher_id' => $researcher->id, 'entity_type' => 'information_object'],
                ['content' => $request->input('notes') ?: null, 'created_at' => now()]
            );
            return redirect()->route('research.evidence-viewer', ['object_id' => $objectId])->with('success', 'Notes saved.');
        }

        // Get tags from annotations
        $tags = DB::table('research_annotation')
            ->where('object_id', $objectId)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn($t) => explode(',', $t))
            ->map(fn($t) => trim($t))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return view('research::research.evidence-viewer', compact('source', 'imageUrl', 'notes', 'tags'));
    }

    // =========================================================================
    // AJAX: SEARCH ITEMS
    // =========================================================================

    public function searchItems(Request $request)
    {
        $query = trim($request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('ioi.title', 'LIKE', '%' . $query . '%')
                  ->orWhere('io.identifier', 'LIKE', '%' . $query . '%');
            })
            ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
            ->orderBy('ioi.title')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                $hasChildren = DB::table('information_object')
                    ->where('parent_id', $item->id)->exists();
                return [
                    'id' => $item->id,
                    'title' => $item->title ?: 'Untitled [' . $item->id . ']',
                    'identifier' => $item->identifier,
                    'slug' => $item->slug,
                    'has_children' => $hasChildren,
                ];
            })
            ->toArray();

        return response()->json(['items' => $items]);
    }

    // =========================================================================
    // AJAX: ADD TO COLLECTION
    // =========================================================================

    // addToCollection() and createCollectionAjax() moved to
    // ResearchCollectionsController (serial integration, issue #1269).
    // Routes repointed in routes/web.php.

    // =========================================================================
    // API KEYS
    // =========================================================================

    public function apiKeys(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return redirect()->route('research.dashboard')->with('error', 'Must be an approved researcher');
        }

        $apiKeys = $this->service->getApiKeys($researcher->id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'generate') {
                $result = $this->service->generateApiKey(
                    $researcher->id,
                    trim($request->input('name', 'API Key')),
                    $request->input('permissions', []),
                    $request->input('expires_at') ?: null
                );
                return redirect()->route('research.apiKeys')
                    ->with('success', 'API key generated successfully. <br><code id="apiKeyValue" class="user-select-all fs-6">' . e($result['key']) . '</code><br><small class="text-muted">Copy this key now — it will not be shown again.</small>');
            }
            if ($action === 'revoke') {
                $this->service->revokeApiKey((int) $request->input('key_id'), $researcher->id);
                return redirect()->route('research.apiKeys')->with('success', 'API key revoked');
            }
        }

        return view('research::research.api-keys', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher', 'apiKeys')
        ));
    }

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
    // DEDICATED ROUTE METHODS (Journal)
    // =========================================================================

    public function createJournalEntry()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')
            ->orderBy('p.title')->get()->toArray();

        $filters = ['project_id' => null, 'entry_type' => null, 'date_from' => null, 'date_to' => null, 'search' => null];
        $entries = [];

        $journals = DB::table('research_journal')
            ->where('researcher_id', $researcher->id)
            ->orderByDesc('updated_at')->get()->toArray();

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters', 'journals'),
            ['showCreateForm' => true]
        ));
    }

    public function showJournalEntry(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        return redirect()->route('research.journalEntry', $id);
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Projects)
    // =========================================================================

    // createProject() / storeProject() moved to ResearchProjectsController
    // (project-subsystem stage, issue #1269). Routes re-pointed in routes/web.php.

    // storeAnnotation() / updateAnnotation() / destroyAnnotation() moved to
    // ResearchAnnotationsController (stage 2, issue #1253).

    // =========================================================================
    // DEDICATED ROUTE METHODS (Researchers Admin)
    // =========================================================================

    public function resetPassword(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $newPassword = \Illuminate\Support\Str::random(12);
        // Use the provisioner so the password uses the canonical auth scheme
        // (salt + sha1 + argon2), not a one-off bcrypt that login cannot verify.
        app(\AhgResearch\Contracts\UserProvisionerInterface::class)
            ->setPassword($researcher->user_id, $newPassword);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Password reset. New password: <strong>' . e($newPassword) . '</strong> - share this with the researcher securely.');
    }

    public function suspendResearcher(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        DB::table('research_researcher')->where('id', $id)->update([
            'status' => 'suspended',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        app(\AhgResearch\Contracts\UserProvisionerInterface::class)->deactivateUser($researcher->user_id);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher suspended');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Saved Searches) -> extracted to
    // ResearchSavedSearchesController (issue #1269)
    // =========================================================================

    // =========================================================================
    // DEDICATED ROUTE METHODS (Public Registration)
    // =========================================================================

    public function storePublicRegistration(Request $request)
    {
        return $this->publicRegister($request);
    }

    // =========================================================================
    // RENEWAL
    // =========================================================================

    public function renewal(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if (!in_array($researcher->status, ['expired', 'approved'])) {
            return redirect()->route('research.profile')->with('error', 'Renewal not available for your current status');
        }

        if ($request->isMethod('post')) {
            DB::table('access_request')->insert([
                'request_type' => 'researcher',
                'scope_type' => 'renewal',
                'user_id' => Auth::id(),
                'reason' => trim($request->input('reason', '')) ?: 'Researcher registration renewal request',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.profile')
                ->with('success', 'Renewal request submitted. You will be notified when reviewed.');
        }

        return view('research::research.renewal', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher')
        ));
    }

    // =========================================================================
    // FINDING AID EXPORT
    // =========================================================================

    /**
     * Export finding aid as PDF or DOCX (generates HTML with print styling).
     */
    /**
     * Export notes as PDF (printable HTML) or CSV.
     */
    public function exportNotes(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $format = $request->input('format', 'pdf');
        $ids = $request->input('ids') ? explode(',', $request->input('ids')) : [];
        $id = $request->input('id');
        if ($id) $ids = [(int) $id];

        $query = DB::table('research_annotation')
            ->where('researcher_id', $researcher->id);
        if (!empty($ids)) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        $notes = $query->orderBy('created_at', 'desc')->get();

        if ($format === 'csv') {
            $filename = 'research-notes.csv';
            return response()->stream(function () use ($notes) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Title', 'Content', 'Tags', 'Visibility', 'Created']);
                foreach ($notes as $n) {
                    fputcsv($out, [$n->title, strip_tags($n->content ?? ''), $n->tags, $n->visibility, $n->created_at]);
                }
                fclose($out);
            }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
        }

        // PDF = printable HTML
        return view('research::research.export-notes-pdf', compact('notes', 'researcher'));
    }

    public function exportFindingAid(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collectionId = (int) $request->input('id');
        $format = $request->input('format', 'pdf');

        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Collection not found');
        }

        $data = $this->getCollectionFindingAidData($collectionId);

        if ($format === 'pdf') {
            // Render as printable HTML — user can print to PDF
            return view('research::research.finding-aid', [
                'collection' => $collection,
                'items' => $data,
                'researcher' => $researcher,
                'format' => 'pdf',
            ]);
        }

        // CSV fallback for DOCX (basic export)
        $filename = ($collection->name ?? 'finding-aid') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        return response()->stream(function () use ($data, $collection) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Finding Aid: ' . $collection->name]);
            fputcsv($out, []);
            fputcsv($out, ['Identifier', 'Title', 'Level', 'Repository', 'Scope & Content', 'Extent', 'Access Conditions', 'Notes']);
            foreach ($data as $item) {
                fputcsv($out, [
                    $item->identifier, $item->title, $item->level_of_description,
                    $item->repository_name, $item->scope_and_content, $item->extent_and_medium,
                    $item->access_conditions, $item->researcher_notes,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Generate HTML finding aid (viewable in browser).
     */
    public function generateFindingAid(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collectionId = (int) $request->input('id');
        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Collection not found');
        }

        $data = $this->getCollectionFindingAidData($collectionId);

        return view('research::research.finding-aid', [
            'collection' => $collection,
            'items' => $data,
            'researcher' => $researcher,
            'format' => 'html',
        ]);
    }

    /**
     * Get enriched collection items for finding aid export.
     */
    private function getCollectionFindingAidData(int $collectionId): array
    {
        $culture = app()->getLocale();

        return DB::table('research_collection_item as ci')
            ->join('information_object as io', 'ci.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as lod', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'lod.id')->where('lod.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as repo', function ($j) use ($culture) {
                $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('ci.collection_id', $collectionId)
            ->select(
                'io.id', 'io.identifier', 's.slug',
                'ioi.title', 'ioi.scope_and_content', 'ioi.extent_and_medium',
                'ioi.archival_history', 'ioi.arrangement', 'ioi.access_conditions',
                'ioi.reproduction_conditions', 'ioi.physical_characteristics',
                'lod.name as level_of_description',
                'repo.authorized_form_of_name as repository_name',
                'ci.notes as researcher_notes', 'ci.created_at'
            )
            ->orderBy('ci.created_at')
            ->get()->toArray();
    }

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

    // =========================================================================
    // MOBILE / PWA + OFFLINE SYNC
    // =========================================================================

    public function mobileHome(Request $request)
    {
        $researcher = Auth::check() ? $this->service->getResearcherByUserId(Auth::id()) : null;

        $readingList = [];
        if ($researcher) {
            try {
                $readingList = DB::table('research_collection_item as ci')
                    ->join('research_collection as c', 'ci.collection_id', '=', 'c.id')
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('ci.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                    })
                    ->leftJoin('slug', 'ci.object_id', '=', 'slug.object_id')
                    ->where('c.researcher_id', $researcher->id)
                    ->orderByDesc('ci.created_at')
                    ->limit(50)
                    ->select('ci.object_id', 'ioi.title', 'slug.slug', 'c.name as collection_name')
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {}
        }

        return view('research::research.mobile-home', compact('researcher', 'readingList'));
    }

    public function offlineSync(Request $request)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload['queue'])) {
            return response()->json(['applied' => 0, 'conflicts' => 0]);
        }

        $applied = 0;
        $conflicts = 0;
        $logId = DB::table('research_offline_sync_log')->insertGetId([
            'researcher_id'   => (int) $researcher->id,
            'sync_started_at' => date('Y-m-d H:i:s'),
            'queued_count'    => count($payload['queue']),
            'payload_hash'    => hash('sha256', json_encode($payload['queue'])),
        ]);

        foreach ($payload['queue'] as $item) {
            $kind = $item['kind'] ?? null;
            try {
                if ($kind === 'journal_entry') {
                    DB::table('research_journal_entry')->insert([
                        'researcher_id' => (int) $researcher->id,
                        'project_id'    => $item['project_id'] ?? null,
                        'entry_type'    => $item['entry_type'] ?? 'note',
                        'entry_date'    => $item['entry_date'] ?? date('Y-m-d'),
                        'title'         => mb_substr((string) ($item['title'] ?? ''), 0, 255),
                        'content'       => (string) ($item['content'] ?? ''),
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $applied++;
                } elseif ($kind === 'annotation') {
                    DB::table('ahg_iiif_annotation')->insert([
                        'uuid'                  => $item['uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                        'target_iri'            => (string) ($item['target_iri'] ?? ''),
                        'information_object_id' => $item['information_object_id'] ?? null,
                        'project_id'            => $item['project_id'] ?? null,
                        'visibility'            => $item['visibility'] ?? 'private',
                        'body_json'             => json_encode($item['body'] ?? []),
                        'created_by'            => Auth::id(),
                        'updated_by'            => Auth::id(),
                        'created_at'            => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                    ]);
                    $applied++;
                } else {
                    $conflicts++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
            }
        }

        DB::table('research_offline_sync_log')->where('id', $logId)->update([
            'sync_completed_at' => date('Y-m-d H:i:s'),
            'applied_count'     => $applied,
            'conflict_count'    => $conflicts,
        ]);

        return response()->json([
            'applied'   => $applied,
            'conflicts' => $conflicts,
            'log_id'    => $logId,
        ]);
    }

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

    public function analytics(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $from = $request->input('from');
        $to   = $request->input('to');

        $data = app(\AhgResearch\Services\ResearchAnalyticsService::class)->dashboard($from, $to);

        return view('research::research.analytics', array_merge(
            $this->getSidebarData('analytics'),
            compact('data')
        ));
    }

    public function crossFondsQuery(Request $request)
    {
        $researcher = Auth::check() ? $this->service->getResearcherByUserId(Auth::id()) : null;
        $svc = app(\AhgResearch\Services\CrossFondsQueryService::class);

        $fondsList = $svc->availableFonds();
        $query = trim((string) $request->input('q', ''));
        $selected = array_map('intval', (array) $request->input('fonds', []));
        $expand = (bool) $request->input('expand');

        $result = null;
        if ($query !== '') {
            $result = $svc->query($query, $selected, $researcher->id ?? null, [
                'expand' => $expand,
                'top_k'  => 30,
            ]);
        }

        return view('research::research.cross-fonds-query', array_merge(
            $this->getSidebarData('crossFonds'),
            compact('fondsList', 'query', 'selected', 'expand', 'result')
        ));
    }
}
