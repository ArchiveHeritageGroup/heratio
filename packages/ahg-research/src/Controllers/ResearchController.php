<?php

/**
 * ResearchController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
    protected ResearchService $service;

    public function __construct()
    {
        $this->service = new ResearchService();
    }

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

    protected function getSidebarData(string $active): array
    {
        $unreadNotifications = 0;
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
            }
        }
        return [
            'sidebarActive' => $active,
            'unreadNotifications' => $unreadNotifications,
        ];
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
                    $salt = md5(rand(100000, 999999) . $email);
                    $sha1Hash = sha1($salt . $password);
                    $passwordHash = password_hash($sha1Hash, PASSWORD_ARGON2I);

                    DB::table('user')->where('id', $existingUser->id)->update([
                        'username' => $username,
                        'password_hash' => $passwordHash,
                        'salt' => $salt,
                        'active' => 0,
                    ]);
                    $userId = $existingUser->id;
                } else {
                    $userId = $this->createAtomUser($username, $email, $password);
                }

                if (!DB::table('acl_user_group')->where('user_id', $userId)->where('group_id', 99)->exists()) {
                    DB::table('acl_user_group')->insert(['user_id' => $userId, 'group_id' => 99]);
                }

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
        $salt = md5(rand(100000, 999999) . $email);
        $sha1Hash = sha1($salt . $password);
        $passwordHash = password_hash($sha1Hash, PASSWORD_ARGON2I);
        $now = date('Y-m-d H:i:s');

        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('actor')->insert([
            'id' => $objectId,
            'corporate_body_identifiers' => null,
            'entity_type_id' => null,
            'description_status_id' => null,
            'description_detail_id' => null,
            'description_identifier' => null,
            'source_standard' => null,
            'source_culture' => 'en',
        ]);

        DB::table('user')->insert([
            'id' => $objectId,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'salt' => $salt,
            'active' => 0,
        ]);

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => preg_replace('/[^a-zA-Z0-9-]/', '-', $username),
        ]);

        return $objectId;
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
            $this->service->updateResearcher($researcher->id, [
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
            ]);
            return redirect()->route('research.profile')->with('success', 'Profile updated');
        }

        $bookings = $this->service->getResearcherBookings($researcher->id);
        $collections = $this->service->getCollections($researcher->id);
        $savedSearches = $this->service->getSavedSearches($researcher->id);

        return view('research::research.profile', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher', 'bookings', 'collections', 'savedSearches')
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
            if ($action === 'approve') {
                $this->service->approveResearcher($id, Auth::id());
                DB::table('user')->where('id', $researcher->user_id)->update(['active' => 1]);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Approved');
            } elseif ($action === 'suspend') {
                DB::table('research_researcher')->where('id', $id)->update(['status' => 'suspended']);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Suspended');
            }
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
        DB::table('user')->where('id', $researcher->user_id)->update(['active' => 1]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher approved and account activated');
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
        DB::table('user')->where('id', $researcher->user_id)->update(['active' => 0]);

        return redirect()->route('research.researchers')
            ->with('success', 'Researcher registration rejected and archived');
    }

    // =========================================================================
    // BOOKINGS
    // =========================================================================

    public function bookings()
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms();
        $pendingBookings = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.status', 'pending')
            ->select('b.*', 'b.booking_date as date', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"), 'r.email', 'rm.name as room_name')
            ->orderBy('b.booking_date')->get()->toArray();
        $upcomingBookings = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.status', 'confirmed')->where('b.booking_date', '>=', date('Y-m-d'))
            ->select('b.*', 'b.booking_date as date', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"), 'rm.name as room_name')
            ->orderBy('b.booking_date')->limit(20)->get()->toArray();

        return view('research::research.bookings', array_merge(
            $this->getSidebarData('bookings'),
            compact('rooms', 'pendingBookings', 'upcomingBookings')
        ));
    }

    public function book(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return redirect()->route('research.dashboard')->with('error', 'Must be approved researcher');
        }

        $rooms = $this->service->getReadingRooms();
        $objectSlug = $request->input('object');
        $object = null;
        if ($objectSlug) {
            $object = DB::table('slug')
                ->join('information_object_i18n as i18n', function ($join) {
                    $join->on('slug.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })->where('slug.slug', $objectSlug)
                ->select('slug.object_id', 'i18n.title')->first();
        }

        if ($request->isMethod('post')) {
            $bookingId = $this->service->createBooking([
                'researcher_id' => $researcher->id,
                'reading_room_id' => $request->input('room_id'),
                'booking_date' => $request->input('date'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'purpose' => $request->input('purpose'),
                'notes' => $request->input('notes'),
            ]);
            foreach ($request->input('materials', []) as $objectId) {
                $this->service->addMaterialRequest($bookingId, (int) $objectId);
            }
            return redirect()->route('research.viewBooking', $bookingId)
                ->with('success', 'Booking submitted');
        }

        return view('research::research.book', array_merge(
            $this->getSidebarData('book'),
            compact('researcher', 'rooms', 'objectSlug', 'object')
        ));
    }

    public function viewBooking(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'confirm') {
                $this->service->confirmBooking($id, Auth::id());
                return redirect()->route('research.viewBooking', $id)->with('success', 'Booking confirmed');
            } elseif ($action === 'cancel') {
                $this->service->cancelBooking($id, 'Cancelled by staff');
                return redirect()->route('research.viewBooking', $id)->with('success', 'Booking cancelled');
            } elseif ($action === 'noshow') {
                DB::table('research_booking')->where('id', $id)->update(['status' => 'no_show']);
                return redirect()->route('research.viewBooking', $id)->with('success', 'Marked as no-show');
            }
        }

        $materials = DB::table('research_material_request as m')
            ->leftJoin('information_object_i18n as ioi18n', function ($j) {
                $j->on('m.object_id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'm.object_id', '=', 'slug.object_id')
            ->where('m.booking_id', $id)
            ->select('m.*', 'ioi18n.title as description', 'slug.slug')
            ->get();

        $isAdmin = Auth::check() && \AhgCore\Services\AclService::isAdministrator(Auth::user());

        return view('research::research.view-booking', array_merge(
            $this->getSidebarData('bookings'),
            compact('booking', 'materials', 'isAdmin')
        ));
    }

    public function checkIn(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        DB::table('research_booking')->where('id', $id)->update([
            'checked_in_at' => date('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);
        return redirect()->route('research.viewBooking', $id)->with('success', 'Researcher checked in');
    }

    public function checkOut(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        DB::table('research_booking')->where('id', $id)->update([
            'checked_out_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        DB::table('research_material_request')
            ->where('booking_id', $id)
            ->where('status', '!=', 'returned')
            ->update(['status' => 'returned', 'returned_at' => date('Y-m-d H:i:s')]);
        return redirect()->route('research.bookings')->with('success', 'Researcher checked out');
    }

    // =========================================================================
    // WORKSPACE
    // =========================================================================

    public function workspace(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collections = $this->service->getCollections($researcher->id);
        $savedSearches = $this->service->getSavedSearches($researcher->id);
        $annotations = $this->service->getAnnotations($researcher->id);

        $upcomingBookings = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcher->id)
            ->where('b.booking_date', '>=', date('Y-m-d'))
            ->whereIn('b.status', ['pending', 'confirmed'])
            ->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date')->orderBy('b.start_time')
            ->limit(5)->get()->toArray();

        $pastBookings = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcher->id)
            ->where(function ($q) {
                $q->where('b.booking_date', '<', date('Y-m-d'))
                  ->orWhere('b.status', 'completed');
            })
            ->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date', 'desc')->limit(5)->get()->toArray();

        $stats = [
            'total_bookings' => DB::table('research_booking')->where('researcher_id', $researcher->id)->count(),
            'total_collections' => count($collections),
            'total_saved_searches' => count($savedSearches),
            'total_annotations' => count($annotations),
            'total_items' => DB::table('research_collection_item as ci')
                ->join('research_collection as c', 'ci.collection_id', '=', 'c.id')
                ->where('c.researcher_id', $researcher->id)->count(),
        ];

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'create_collection') {
                $name = trim($request->input('collection_name'));
                if ($name) {
                    $this->service->createCollection($researcher->id, [
                        'name' => $name,
                        'description' => trim($request->input('collection_description')),
                        'is_public' => $request->input('is_public') ? 1 : 0,
                    ]);
                    return redirect()->route('research.workspace')->with('success', 'Collection created successfully.');
                }
            }
        }

        return view('research::research.workspace', array_merge(
            $this->getSidebarData('workspace'),
            compact('researcher', 'collections', 'savedSearches', 'annotations', 'upcomingBookings', 'pastBookings', 'stats')
        ));
    }

    // =========================================================================
    // SAVED SEARCHES
    // =========================================================================

    public function savedSearches(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'save') {
                $this->service->saveSearch($researcher->id, [
                    'name' => $request->input('name'),
                    'search_query' => $request->input('search_query'),
                ]);
            } elseif ($action === 'delete') {
                $this->service->deleteSavedSearch((int) $request->input('id'), $researcher->id);
            }
            return redirect()->route('research.savedSearches');
        }

        $savedSearches = $this->service->getSavedSearches($researcher->id);

        return view('research::research.saved-searches', array_merge(
            $this->getSidebarData('savedSearches'),
            compact('researcher', 'savedSearches')
        ));
    }

    // =========================================================================
    // COLLECTIONS (Evidence Sets)
    // =========================================================================

    public function collections(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post') && $request->input('do') === 'create') {
            $id = $this->service->createCollection($researcher->id, [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);
            return redirect()->route('research.viewCollection', $id);
        }

        $collections = $this->service->getCollections($researcher->id);

        return view('research::research.collections', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collections')
        ));
    }

    public function viewCollection(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection) abort(404, 'Not found');
        if ($collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');

            if ($action === 'remove') {
                $this->service->removeFromCollection($id, (int) $request->input('object_id'));
                return redirect()->route('research.viewCollection', $id)->with('success', 'Item removed from collection');
            }

            if ($action === 'add_item') {
                $objectId = (int) $request->input('object_id');
                $notes = trim($request->input('notes', ''));
                $includeDescendants = $request->input('include_descendants') ? true : false;
                if ($objectId > 0) {
                    $addedCount = 0;
                    $objectsToAdd = [$objectId];
                    if ($includeDescendants) {
                        $item = DB::table('information_object')->where('id', $objectId)->first();
                        if ($item) {
                            $descendants = DB::table('information_object')
                                ->where('lft', '>', $item->lft)
                                ->where('rgt', '<', $item->rgt)
                                ->pluck('id')->toArray();
                            $objectsToAdd = array_merge($objectsToAdd, $descendants);
                        }
                    }
                    foreach ($objectsToAdd as $oid) {
                        $exists = DB::table('research_collection_item')
                            ->where('collection_id', $id)
                            ->where('object_id', $oid)->exists();
                        if (!$exists) {
                            DB::table('research_collection_item')->insert([
                                'collection_id' => $id,
                                'object_id' => $oid,
                                'notes' => ($oid == $objectId) ? $notes : '',
                                'created_at' => date('Y-m-d H:i:s'),
                            ]);
                            $addedCount++;
                        }
                    }
                    $msg = $addedCount > 0 ? "$addedCount item(s) added to collection" : 'Item(s) already in collection';
                    $type = $addedCount > 0 ? 'success' : 'error';
                    return redirect()->route('research.viewCollection', $id)->with($type, $msg);
                }
            }

            if ($action === 'update_notes') {
                DB::table('research_collection_item')
                    ->where('collection_id', $id)
                    ->where('object_id', (int) $request->input('object_id'))
                    ->update(['notes' => trim($request->input('notes'))]);
                return redirect()->route('research.viewCollection', $id)->with('success', 'Notes updated');
            }

            if ($action === 'update') {
                $name = trim($request->input('name'));
                if ($name) {
                    DB::table('research_collection')->where('id', $id)->update([
                        'name' => $name,
                        'description' => trim($request->input('description')),
                        'is_public' => $request->input('is_public') ? 1 : 0,
                    ]);
                    return redirect()->route('research.viewCollection', $id)->with('success', 'Collection updated');
                }
            }

            if ($action === 'delete') {
                DB::table('research_collection_item')->where('collection_id', $id)->delete();
                DB::table('research_collection')->where('id', $id)->delete();
                return redirect()->route('research.collections')->with('success', 'Collection deleted');
            }
        }

        return view('research::research.view-collection', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collection')
        ));
    }

    // =========================================================================
    // ANNOTATIONS
    // =========================================================================

    public function annotations(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $action = $request->input('do');

            if ($action === 'delete') {
                $this->service->deleteAnnotation((int) $request->input('id'), $researcher->id);
                return redirect()->route('research.annotations')->with('success', 'Note deleted');
            }

            if ($action === 'create') {
                $content = trim($request->input('content'));
                $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
                $entityType = $request->input('entity_type', 'information_object');
                $visibility = $request->input('visibility', 'private');
                $contentFormat = $request->input('content_format', 'text');

                if ($content) {
                    DB::table('research_annotation')->insert([
                        'researcher_id' => $researcher->id,
                        'object_id' => ((int) $request->input('object_id')) ?: null,
                        'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                        'collection_id' => ((int) $request->input('collection_id')) ?: null,
                        'title' => trim($request->input('title')),
                        'content' => $content,
                        'tags' => trim($request->input('tags', '')) ?: null,
                        'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                        'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    return redirect()->route('research.annotations')->with('success', 'Note created');
                }
            }

            if ($action === 'update') {
                $id = (int) $request->input('id');
                $content = trim($request->input('content'));
                $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
                $entityType = $request->input('entity_type', 'information_object');
                $visibility = $request->input('visibility', 'private');
                $contentFormat = $request->input('content_format', 'text');

                if ($content) {
                    DB::table('research_annotation')
                        ->where('id', $id)
                        ->where('researcher_id', $researcher->id)
                        ->update([
                            'title' => trim($request->input('title')),
                            'content' => $content,
                            'object_id' => ((int) $request->input('object_id')) ?: null,
                            'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                            'collection_id' => ((int) $request->input('collection_id')) ?: null,
                            'tags' => trim($request->input('tags', '')) ?: null,
                            'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                            'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                        ]);
                    return redirect()->route('research.annotations')->with('success', 'Note updated');
                }
            }
        }

        $q = $request->input('q');
        $visibility = $request->input('visibility');
        $tag = $request->input('tag');

        $annotations = $q
            ? $this->service->searchAnnotations($researcher->id, $q)
            : $this->service->getAnnotations($researcher->id);

        if ($visibility) {
            $annotations = array_filter($annotations, fn($a) => ($a->visibility ?? 'private') === $visibility);
        }
        if ($tag) {
            $annotations = array_filter($annotations, function ($a) use ($tag) {
                if (empty($a->tags)) return false;
                $tags = array_map('trim', explode(',', $a->tags));
                return in_array($tag, $tags);
            });
        }
        $annotations = array_values($annotations);

        $researchCollections = DB::table('research_collection')
            ->where('researcher_id', $researcher->id)
            ->orderBy('name')->get();

        return view('research::research.annotations', array_merge(
            $this->getSidebarData('annotations'),
            compact('researcher', 'annotations', 'researchCollections')
        ));
    }

    // =========================================================================
    // CITATIONS
    // =========================================================================

    public function cite(Request $request, string $slug)
    {
        $object = DB::table('slug')->where('slug', $slug)->first();
        if (!$object) abort(404);

        $styles = ['chicago', 'mla', 'turabian', 'apa', 'harvard', 'unisa'];
        $citations = [];
        foreach ($styles as $style) {
            $citations[$style] = $this->service->generateCitation($object->object_id, $style);
        }

        $researcherId = null;
        if (Auth::check()) {
            $r = $this->service->getResearcherByUserId(Auth::id());
            if ($r) $researcherId = $r->id;
        }
        foreach ($citations as $style => $data) {
            if (!isset($data['error'])) {
                $this->service->logCitation($researcherId, $object->object_id, $style, $data['citation']);
            }
        }

        return view('research::research.cite', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('citations', 'styles')
        ));
    }

    // =========================================================================
    // PROJECTS
    // =========================================================================

    public function projects(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $status = $request->input('status');
        $projects = DB::table('research_project as p')
            ->where(function ($q) use ($researcher) {
                $q->where('p.owner_id', $researcher->id)
                  ->orWhereExists(function ($sub) use ($researcher) {
                      $sub->select(DB::raw(1))
                          ->from('research_project_collaborator')
                          ->whereColumn('research_project_collaborator.project_id', 'p.id')
                          ->where('research_project_collaborator.researcher_id', $researcher->id)
                          ->where('research_project_collaborator.status', 'accepted');
                  });
            });

        if ($status) $projects->where('p.status', $status);
        $projects = $projects->orderBy('p.created_at', 'desc')->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $projectId = DB::table('research_project')->insertGetId([
                'owner_id' => $researcher->id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'project_type' => $request->input('project_type', 'personal'),
                'institution' => $request->input('institution'),
                'start_date' => $request->input('start_date'),
                'expected_end_date' => $request->input('expected_end_date'),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Add creator as collaborator/owner
            DB::table('research_project_collaborator')->insert([
                'project_id' => $projectId,
                'researcher_id' => $researcher->id,
                'role' => 'owner',
                'status' => 'accepted',
                'invited_at' => date('Y-m-d H:i:s'),
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);

            return redirect()->route('research.viewProject', $projectId)->with('success', 'Project created');
        }

        return view('research::research.projects', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'projects', 'status')
        ));
    }

    public function viewProject(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        $resources = DB::table('research_project_resource')
            ->where('project_id', $id)
            ->orderBy('added_at', 'desc')
            ->get()->toArray();

        $milestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        $activities = DB::table('research_activity_log')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()->toArray();

        return view('research::research.view-project', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'project', 'collaborators', 'resources', 'milestones', 'activities')
        ));
    }

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

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters')
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
                'time_spent_minutes' => $request->input('time_spent_minutes') ?: null,
                'tags' => $request->input('tags'),
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

    public function bibliographies(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $bibliographies = DB::table('research_bibliography')
            ->where('researcher_id', $researcher->id)
            ->orderBy('name')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $bibliographyId = DB::table('research_bibliography')->insertGetId([
                'researcher_id' => $researcher->id,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'citation_style' => $request->input('citation_style', 'chicago'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.viewBibliography', $bibliographyId)->with('success', 'Bibliography created');
        }

        return view('research::research.bibliographies', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('researcher', 'bibliographies')
        ));
    }

    public function viewBibliography(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $bibliography = DB::table('research_bibliography')
            ->where('id', $id)
            ->where('researcher_id', $researcher->id)
            ->first();
        if (!$bibliography) abort(404);

        $entries = DB::table('research_bibliography_entry')
            ->where('bibliography_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_entry') {
                $objectId = (int) $request->input('object_id');
                if ($objectId) {
                    $obj = DB::table('information_object_i18n')
                        ->where('id', $objectId)->where('culture', 'en')->first();
                    $maxOrder = DB::table('research_bibliography_entry')
                        ->where('bibliography_id', $id)->max('sort_order') ?? 0;
                    DB::table('research_bibliography_entry')->insert([
                        'bibliography_id' => $id,
                        'object_id' => $objectId,
                        'title' => $obj->title ?? 'Untitled',
                        'sort_order' => $maxOrder + 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry added');
                }
            }

            if ($action === 'remove_entry') {
                DB::table('research_bibliography_entry')
                    ->where('id', (int) $request->input('entry_id'))
                    ->where('bibliography_id', $id)
                    ->delete();
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry removed');
            }

            if ($action === 'delete') {
                DB::table('research_bibliography_entry')->where('bibliography_id', $id)->delete();
                DB::table('research_bibliography')->where('id', $id)->delete();
                return redirect()->route('research.bibliographies')->with('success', 'Bibliography deleted');
            }
        }

        return view('research::research.view-bibliography', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('researcher', 'bibliography', 'entries')
        ));
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function reports(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $query = DB::table('research_report')
            ->where('researcher_id', $researcher->id);
        if ($request->input('status')) $query->where('status', $request->input('status'));
        $reports = $query->orderBy('created_at', 'desc')->get()->toArray();
        $currentStatus = $request->input('status');

        return view('research::research.reports', array_merge(
            $this->getSidebarData('reports'),
            compact('researcher', 'reports', 'currentStatus')
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
                DB::table('research_report')->where('id', $id)->update(['status' => $request->input('status')]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Status updated');
            }
        }

        return view('research::research.view-report', array_merge(
            $this->getSidebarData('reports'),
            compact('researcher', 'report')
        ));
    }

    // =========================================================================
    // REPRODUCTIONS
    // =========================================================================

    public function reproductions(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $query = DB::table('research_reproduction_request')
            ->where('researcher_id', $researcher->id);
        if ($request->input('status')) $query->where('status', $request->input('status'));
        $requests = $query->orderBy('created_at', 'desc')->get()->toArray();

        return view('research::research.reproductions', array_merge(
            $this->getSidebarData('reproductions'),
            compact('researcher', 'requests')
        ));
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

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
            }
            return redirect()->route('research.notifications');
        }

        $notifications = DB::table('research_notification')
            ->where('researcher_id', $researcher->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()->toArray();

        return view('research::research.notifications', array_merge(
            $this->getSidebarData('notifications'),
            compact('researcher', 'notifications')
        ));
    }

    // =========================================================================
    // ADMIN: READING ROOMS
    // =========================================================================

    public function rooms()
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        return view('research::research.rooms', array_merge(
            $this->getSidebarData('rooms'),
            compact('rooms')
        ));
    }

    public function editRoom(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $id = (int) $request->input('id');
        $room = $id ? $this->service->getReadingRoom($id) : null;
        $isNew = !$room;

        if ($request->isMethod('post')) {
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'location' => $request->input('location'),
                'capacity' => (int) $request->input('capacity', 10),
                'description' => $request->input('description'),
                'amenities' => $request->input('amenities'),
                'rules' => $request->input('rules'),
                'opening_time' => $request->input('opening_time', '09:00:00'),
                'closing_time' => $request->input('closing_time', '17:00:00'),
                'days_open' => $request->input('days_open', 'Mon,Tue,Wed,Thu,Fri'),
                'is_active' => $request->input('is_active') ? 1 : 0,
                'advance_booking_days' => (int) $request->input('advance_booking_days', 14),
                'max_booking_hours' => (int) $request->input('max_booking_hours', 4),
                'cancellation_hours' => (int) $request->input('cancellation_hours', 24),
            ];
            if ($id && $room) {
                DB::table('research_reading_room')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::table('research_reading_room')->insert($data);
            }
            return redirect()->route('research.rooms')->with('success', $isNew ? 'Reading room created' : 'Reading room updated');
        }

        return view('research::research.edit-room', array_merge(
            $this->getSidebarData('rooms'),
            compact('room', 'isNew')
        ));
    }

    // =========================================================================
    // ADMIN: SEATS, EQUIPMENT, RETRIEVAL QUEUE, WALK-IN
    // =========================================================================

    public function seats(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;
        $seats = $roomId ? DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->get()->toArray() : [];

        return view('research::research.seats', array_merge(
            $this->getSidebarData('seats'),
            compact('rooms', 'roomId', 'currentRoom', 'seats')
        ));
    }

    public function equipment(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;
        $equipment = $roomId ? DB::table('research_equipment')->where('reading_room_id', $roomId)->get()->toArray() : [];

        return view('research::research.equipment', array_merge(
            $this->getSidebarData('equipment'),
            compact('rooms', 'roomId', 'currentRoom', 'equipment')
        ));
    }

    public function retrievalQueue(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms();
        $requests = DB::table('research_material_request as m')
            ->join('research_booking as b', 'm.booking_id', '=', 'b.id')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->whereIn('m.status', ['requested', 'in_transit'])
            ->select('m.*', 'b.booking_date', 'b.start_time', 'r.first_name', 'r.last_name', 'i18n.title as object_title')
            ->orderBy('b.booking_date')
            ->get()->toArray();

        return view('research::research.retrieval-queue', array_merge(
            $this->getSidebarData('retrievalQueue'),
            compact('rooms', 'requests')
        ));
    }

    public function walkIn(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms();
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;
        $currentWalkIns = $roomId
            ? DB::table('research_walk_in_visitor')
                ->where('reading_room_id', $roomId)
                ->where('visit_date', date('Y-m-d'))
                ->whereNull('checked_out_at')
                ->orderBy('checked_in_at')
                ->get()->toArray()
            : [];

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'register') {
                DB::table('research_walk_in_visitor')->insert([
                    'reading_room_id' => $roomId,
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'phone' => $request->input('phone'),
                    'id_type' => $request->input('id_type'),
                    'id_number' => $request->input('id_number'),
                    'organization' => $request->input('organization'),
                    'purpose' => $request->input('purpose'),
                    'research_topic' => $request->input('research_topic'),
                    'rules_acknowledged' => $request->input('rules_acknowledged') ? 1 : 0,
                    'visit_date' => date('Y-m-d'),
                    'checked_in_at' => date('Y-m-d H:i:s'),
                    'checked_in_by' => Auth::id(),
                ]);
                return redirect()->route('research.walkIn', ['room_id' => $roomId])
                    ->with('success', 'Walk-in visitor registered');
            }
            if ($action === 'checkout') {
                DB::table('research_walk_in_visitor')
                    ->where('id', (int) $request->input('visitor_id'))
                    ->update([
                        'checked_out_at' => date('Y-m-d H:i:s'),
                        'checked_out_by' => Auth::id(),
                    ]);
                return redirect()->route('research.walkIn', ['room_id' => $roomId])
                    ->with('success', 'Visitor checked out');
            }
        }

        return view('research::research.walk-in', array_merge(
            $this->getSidebarData('walkIn'),
            compact('rooms', 'roomId', 'currentRoom', 'currentWalkIns')
        ));
    }

    // =========================================================================
    // ADMIN: RESEARCHER TYPES
    // =========================================================================

    public function adminTypes()
    {
        if (!Auth::check()) return redirect()->route('login');
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
            'total_annotations' => DB::table('research_annotation')->count(),
        ];

        return view('research::research.admin-statistics', array_merge(
            $this->getSidebarData('adminStatistics'),
            compact('stats', 'dateFrom', 'dateTo')
        ));
    }

    // =========================================================================
    // ADMIN: INSTITUTIONS & ACTIVITIES
    // =========================================================================

    public function institutions(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $institutions = DB::table('research_institution')->orderBy('name')->get()->toArray();
        return view('research::research.institutions', array_merge(
            $this->getSidebarData('institutions'),
            compact('institutions')
        ));
    }

    public function activities(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $activities = DB::table('research_activity as a')
            ->leftJoin('research_reading_room as rm', 'a.reading_room_id', '=', 'rm.id')
            ->select('a.*', 'rm.name as room_name')
            ->orderBy('a.start_date', 'desc')
            ->limit(50)
            ->get()->toArray();

        return view('research::research.activities', array_merge(
            $this->getSidebarData('activities'),
            compact('activities')
        ));
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

    public function addToCollection(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return response()->json(['success' => false, 'error' => 'Not an approved researcher']);
        }

        $collectionId = (int) $request->input('collection_id');
        $objectId = (int) $request->input('object_id');
        $notes = $request->input('notes', '');

        $collection = DB::table('research_collection')
            ->where('id', $collectionId)
            ->where('researcher_id', $researcher->id)
            ->first();
        if (!$collection) {
            return response()->json(['success' => false, 'error' => 'Collection not found']);
        }

        $exists = DB::table('research_collection_item')
            ->where('collection_id', $collectionId)
            ->where('object_id', $objectId)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'error' => 'Item already in collection']);
        }

        $this->service->addToCollection($collectionId, $objectId, $notes);
        return response()->json(['success' => true, 'message' => 'Item added to collection']);
    }

    public function createCollectionAjax(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return response()->json(['success' => false, 'error' => 'Not an approved researcher']);
        }

        $name = trim($request->input('name'));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => 'Collection name is required']);
        }

        $collectionId = $this->service->createCollection($researcher->id, [
            'name' => $name,
            'description' => trim($request->input('description', '')),
            'is_public' => $request->input('is_public') ? 1 : 0,
        ]);

        $objectId = (int) $request->input('object_id');
        if ($objectId > 0) {
            $this->service->addToCollection($collectionId, $objectId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Collection created',
            'collection_id' => $collectionId,
        ]);
    }

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
                    ->with('success', 'API key generated. Key: <strong>' . $result['key'] . '</strong> - Save this now, it will not be shown again.');
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

    public function createCollection()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collections = $this->service->getCollections($researcher->id);

        return view('research::research.collections', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collections'),
            ['showCreateForm' => true]
        ));
    }

    public function storeCollection(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $id = $this->service->createCollection($researcher->id, [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('research.viewCollection', $id)
            ->with('success', 'Collection created');
    }

    public function updateCollection(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $name = trim($request->input('name'));
        if ($name) {
            DB::table('research_collection')->where('id', $id)->update([
                'name' => $name,
                'description' => trim($request->input('description')),
                'is_public' => $request->input('is_public') ? 1 : 0,
            ]);
            return redirect()->route('research.viewCollection', $id)->with('success', 'Collection updated');
        }

        return redirect()->route('research.viewCollection', $id)->with('error', 'Name is required');
    }

    public function destroyCollection(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        DB::table('research_collection_item')->where('collection_id', $id)->delete();
        DB::table('research_collection')->where('id', $id)->delete();

        return redirect()->route('research.collections')->with('success', 'Collection deleted');
    }

    public function addItemToCollection(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $objectId = (int) $request->input('object_id');
        $notes = trim($request->input('notes', ''));
        $includeDescendants = $request->input('include_descendants') ? true : false;

        if ($objectId > 0) {
            $addedCount = 0;
            $objectsToAdd = [$objectId];
            if ($includeDescendants) {
                $item = DB::table('information_object')->where('id', $objectId)->first();
                if ($item) {
                    $descendants = DB::table('information_object')
                        ->where('lft', '>', $item->lft)
                        ->where('rgt', '<', $item->rgt)
                        ->pluck('id')->toArray();
                    $objectsToAdd = array_merge($objectsToAdd, $descendants);
                }
            }
            foreach ($objectsToAdd as $oid) {
                $exists = DB::table('research_collection_item')
                    ->where('collection_id', $id)
                    ->where('object_id', $oid)->exists();
                if (!$exists) {
                    DB::table('research_collection_item')->insert([
                        'collection_id' => $id,
                        'object_id' => $oid,
                        'notes' => ($oid == $objectId) ? $notes : '',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $addedCount++;
                }
            }
            $msg = $addedCount > 0 ? "$addedCount item(s) added to collection" : 'Item(s) already in collection';
            $type = $addedCount > 0 ? 'success' : 'error';
            return redirect()->route('research.viewCollection', $id)->with($type, $msg);
        }

        return redirect()->route('research.viewCollection', $id)->with('error', 'No item selected');
    }

    public function removeItemFromCollection(int $collectionId, int $itemId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $this->service->removeFromCollection($collectionId, $itemId);

        return redirect()->route('research.viewCollection', $collectionId)->with('success', 'Item removed from collection');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Bookings)
    // =========================================================================

    public function confirmBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        $this->service->confirmBooking($id, Auth::id());

        return redirect()->route('research.viewBooking', $id)->with('success', 'Booking confirmed');
    }

    public function checkInBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        DB::table('research_booking')->where('id', $id)->update([
            'checked_in_at' => date('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);

        return redirect()->route('research.viewBooking', $id)->with('success', 'Researcher checked in');
    }

    public function checkOutBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        DB::table('research_booking')->where('id', $id)->update([
            'checked_out_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        DB::table('research_material_request')
            ->where('booking_id', $id)
            ->where('status', '!=', 'returned')
            ->update(['status' => 'returned', 'returned_at' => date('Y-m-d H:i:s')]);

        return redirect()->route('research.bookings')->with('success', 'Researcher checked out');
    }

    public function noShowBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        DB::table('research_booking')->where('id', $id)->update(['status' => 'no_show']);

        return redirect()->route('research.viewBooking', $id)->with('success', 'Marked as no-show');
    }

    public function cancelBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        $this->service->cancelBooking($id, 'Cancelled by staff');

        return redirect()->route('research.viewBooking', $id)->with('success', 'Booking cancelled');
    }

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

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters'),
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

    public function createProject()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $status = null;
        $projects = DB::table('research_project as p')
            ->where(function ($q) use ($researcher) {
                $q->where('p.owner_id', $researcher->id)
                  ->orWhereExists(function ($sub) use ($researcher) {
                      $sub->select(DB::raw(1))
                          ->from('research_project_collaborator')
                          ->whereColumn('research_project_collaborator.project_id', 'p.id')
                          ->where('research_project_collaborator.researcher_id', $researcher->id)
                          ->where('research_project_collaborator.status', 'accepted');
                  });
            })
            ->orderBy('p.created_at', 'desc')->get()->toArray();

        return view('research::research.projects', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'projects', 'status'),
            ['showCreateForm' => true]
        ));
    }

    public function storeProject(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $projectId = DB::table('research_project')->insertGetId([
            'owner_id' => $researcher->id,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'project_type' => $request->input('project_type', 'personal'),
            'institution' => $request->input('institution'),
            'start_date' => $request->input('start_date'),
            'expected_end_date' => $request->input('expected_end_date'),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('research_project_collaborator')->insert([
            'project_id' => $projectId,
            'researcher_id' => $researcher->id,
            'role' => 'owner',
            'status' => 'accepted',
            'invited_at' => date('Y-m-d H:i:s'),
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->route('research.viewProject', $projectId)->with('success', 'Project created');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Annotations)
    // =========================================================================

    public function storeAnnotation(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $content = trim($request->input('content'));
        $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
        $entityType = $request->input('entity_type', 'information_object');
        $visibility = $request->input('visibility', 'private');
        $contentFormat = $request->input('content_format', 'text');

        if ($content) {
            DB::table('research_annotation')->insert([
                'researcher_id' => $researcher->id,
                'object_id' => ((int) $request->input('object_id')) ?: null,
                'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                'collection_id' => ((int) $request->input('collection_id')) ?: null,
                'title' => trim($request->input('title')),
                'content' => $content,
                'tags' => trim($request->input('tags', '')) ?: null,
                'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.annotations')->with('success', 'Note created');
        }

        return redirect()->route('research.annotations')->with('error', 'Content is required');
    }

    public function updateAnnotation(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $content = trim($request->input('content'));
        $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
        $entityType = $request->input('entity_type', 'information_object');
        $visibility = $request->input('visibility', 'private');
        $contentFormat = $request->input('content_format', 'text');

        if ($content) {
            DB::table('research_annotation')
                ->where('id', $id)
                ->where('researcher_id', $researcher->id)
                ->update([
                    'title' => trim($request->input('title')),
                    'content' => $content,
                    'object_id' => ((int) $request->input('object_id')) ?: null,
                    'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                    'collection_id' => ((int) $request->input('collection_id')) ?: null,
                    'tags' => trim($request->input('tags', '')) ?: null,
                    'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                    'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                ]);
            return redirect()->route('research.annotations')->with('success', 'Note updated');
        }

        return redirect()->route('research.annotations')->with('error', 'Content is required');
    }

    public function destroyAnnotation(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $this->service->deleteAnnotation($id, $researcher->id);

        return redirect()->route('research.annotations')->with('success', 'Note deleted');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Researchers Admin)
    // =========================================================================

    public function suspendResearcher(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        DB::table('research_researcher')->where('id', $id)->update([
            'status' => 'suspended',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        DB::table('user')->where('id', $researcher->user_id)->update(['active' => 0]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher suspended');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Saved Searches)
    // =========================================================================

    public function storeSavedSearch(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $this->service->saveSearch($researcher->id, [
            'name' => $request->input('name'),
            'search_query' => $request->input('search_query'),
        ]);

        return redirect()->route('research.savedSearches')->with('success', 'Search saved');
    }

    public function runSavedSearch(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $search = DB::table('research_saved_search')
            ->where('id', $id)
            ->where('researcher_id', $researcher->id)
            ->first();

        if (!$search) abort(404, 'Saved search not found');

        // Update last_run_at
        DB::table('research_saved_search')->where('id', $id)->update([
            'last_run_at' => date('Y-m-d H:i:s'),
        ]);

        // Redirect to search results with the saved query
        return redirect('/informationobject/browse?query=' . urlencode($search->search_query));
    }

    public function destroySavedSearch(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $this->service->deleteSavedSearch($id, $researcher->id);

        return redirect()->route('research.savedSearches')->with('success', 'Saved search deleted');
    }

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
    // TEAM WORKSPACES
    // =========================================================================

    public function workspaces(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collaborationService = new CollaborationService();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $collaborationService->createWorkspace($researcher->id, [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'visibility' => $request->input('visibility', 'private'),
            ]);
            return redirect('/research/workspaces')->with('success', 'Workspace created.');
        }

        $workspaces = $collaborationService->getWorkspaces($researcher->id);

        return view('research::research.workspaces', array_merge(
            $this->getSidebarData('workspaces'),
            compact('workspaces')
        ));
    }

    // =========================================================================
    // VALIDATION QUEUE
    // =========================================================================

    public function validationQueue(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $vqService = new ValidationQueueService();

        $filters = [
            'status' => $request->input('status', 'pending'),
            'result_type' => $request->input('result_type'),
            'extraction_type' => $request->input('extraction_type'),
            'min_confidence' => $request->input('min_confidence'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $queue = $vqService->getQueue(null, $filters, $page);
        $stats = $vqService->getQueueStats();
        $pendingCount = $vqService->getPendingCount();

        return view('research::research.validation-queue', array_merge(
            $this->getSidebarData('validationQueue'),
            compact('queue', 'stats', 'pendingCount')
        ));
    }

    public function validateResult(Request $request, $resultId)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $vqService = new ValidationQueueService();
        $action = $request->input('form_action');

        if ($action === 'accept') {
            $success = $vqService->acceptResult((int) $resultId, $researcher->id);
        } elseif ($action === 'reject') {
            $success = $vqService->rejectResult((int) $resultId, $researcher->id, $request->input('reason', ''));
        } elseif ($action === 'modify') {
            $success = $vqService->modifyResult((int) $resultId, $researcher->id, $request->input('modified_data', []));
        } else {
            return response()->json(['error' => 'Invalid action'], 400);
        }

        return response()->json(['success' => $success]);
    }

    public function bulkValidate(Request $request)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $vqService = new ValidationQueueService();
        $resultIds = $request->input('result_ids', []);
        $action = $request->input('form_action');

        if ($action === 'accept') {
            $count = $vqService->bulkAccept($resultIds, $researcher->id);
        } elseif ($action === 'reject') {
            $count = $vqService->bulkReject($resultIds, $researcher->id, $request->input('reason', ''));
        } else {
            return response()->json(['error' => 'Invalid action'], 400);
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    // =========================================================================
    // ENTITY RESOLUTION
    // =========================================================================

    public function entityResolution(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $erService = new EntityResolutionService();

        if ($request->isMethod('post') && $request->input('form_action') === 'propose') {
            $erService->proposeMatch([
                'entity_a_type' => $request->input('entity_a_type'),
                'entity_a_id' => (int) $request->input('entity_a_id'),
                'entity_b_type' => $request->input('entity_b_type'),
                'entity_b_id' => (int) $request->input('entity_b_id'),
                'relationship_type' => $request->input('relationship_type', 'sameAs'),
                'match_method' => $request->input('match_method', 'manual'),
                'confidence' => $request->input('confidence') !== null ? (float) $request->input('confidence') : null,
                'notes' => $request->input('notes'),
                'proposer_id' => $researcher->id,
            ]);
            return redirect('/research/entityResolution')->with('success', 'Match proposed.');
        }

        $filters = [
            'status' => $request->input('status'),
            'entity_type' => $request->input('entity_type'),
            'relationship_type' => $request->input('relationship_type'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $proposals = $erService->getProposals($filters, $page);

        return view('research::research.entity-resolution', array_merge(
            $this->getSidebarData('entityResolution'),
            compact('proposals')
        ));
    }

    public function resolveEntityResolution(Request $request, $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $erService = new EntityResolutionService();
        $status = $request->input('status');
        $success = $erService->resolveMatch((int) $id, $status, $researcher->id);

        return response()->json(['success' => $success]);
    }

    public function entityResolutionConflicts($id)
    {
        $erService = new EntityResolutionService();
        $conflicts = $erService->getConflictingAssertions((int) $id);
        return response()->json(['conflicts' => $conflicts]);
    }

    // =========================================================================
    // ODRL POLICIES
    // =========================================================================

    public function odrlPolicies(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $odrlService = new OdrlService();

        if ($request->isMethod('post')) {
            $formAction = $request->input('form_action');

            if ($formAction === 'create') {
                $constraintsJson = $request->input('constraints_json');
                if ($constraintsJson) {
                    $decoded = json_decode($constraintsJson, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        return redirect('/research/odrlPolicies')->with('error', 'Invalid JSON in constraints.');
                    }
                }

                $odrlService->createPolicy([
                    'target_type' => $request->input('target_type'),
                    'target_id' => (int) $request->input('target_id'),
                    'policy_type' => $request->input('policy_type'),
                    'action_type' => $request->input('action_type'),
                    'constraints_json' => $constraintsJson ?: null,
                    'created_by' => $researcher->id,
                ]);
                return redirect('/research/odrlPolicies')->with('success', 'Policy created.');
            }

            if ($formAction === 'delete') {
                $odrlService->deletePolicy((int) $request->input('policy_id'));
                return redirect('/research/odrlPolicies')->with('success', 'Policy deleted.');
            }
        }

        $filters = [
            'target_type' => $request->input('filter_target_type'),
            'policy_type' => $request->input('filter_policy_type'),
            'action_type' => $request->input('filter_action_type'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $policies = $odrlService->getAllPolicies($filters, 25, ($page - 1) * 25);

        return view('research::research.odrl-policies', array_merge(
            $this->getSidebarData('odrlPolicies'),
            compact('policies')
        ));
    }

    // =========================================================================
    // DOCUMENT TEMPLATES
    // =========================================================================

    public function documentTemplates(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $formAction = $request->input('form_action');

            if ($formAction === 'create') {
                DB::table('research_document_template')->insert([
                    'name' => $request->input('name'),
                    'document_type' => $request->input('document_type'),
                    'description' => $request->input('description'),
                    'fields_json' => $request->input('fields_json') ?: '[]',
                    'created_by' => $researcher->id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect('/research/documentTemplates')->with('success', 'Template created.');
            }

            if ($formAction === 'update') {
                $templateId = (int) $request->input('template_id');
                DB::table('research_document_template')
                    ->where('id', $templateId)
                    ->update([
                        'name' => $request->input('name'),
                        'document_type' => $request->input('document_type'),
                        'description' => $request->input('description'),
                        'fields_json' => $request->input('fields_json') ?: '[]',
                    ]);
                return redirect('/research/documentTemplates')->with('success', 'Template updated.');
            }
        }

        $templates = DB::table('research_document_template')
            ->orderBy('name')
            ->get()
            ->toArray();

        return view('research::research.document-templates', array_merge(
            $this->getSidebarData('documentTemplates'),
            compact('templates')
        ));
    }
}
