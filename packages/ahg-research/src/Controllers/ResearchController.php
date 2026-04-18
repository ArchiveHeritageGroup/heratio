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

            // Admins can edit ID fields
            $isAdmin = DB::table('acl_user_group')
                ->where('user_id', Auth::id())->where('group_id', 100)->exists();
            if ($isAdmin) {
                $data['id_type'] = $request->input('id_type');
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

        $canUseFeatures = $researcher->status === 'approved'
            && (!($researcher->expires_at ?? null) || strtotime($researcher->expires_at) >= time());

        // Weekly activity sparkline (last 7 days)
        $weeklyActivity = [];
        try {
            $weeklyActivity = DB::table('research_access_decision')
                ->where('researcher_id', $researcher->id)
                ->where('evaluated_at', '>=', date('Y-m-d', strtotime('-7 days')))
                ->selectRaw('DATE(evaluated_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(evaluated_at)')
                ->orderBy('date')
                ->get()->toArray();
        } catch (\Exception $e) {}

        return view('research::research.workspace', array_merge(
            $this->getSidebarData('workspace'),
            compact('researcher', 'collections', 'savedSearches', 'annotations', 'upcomingBookings', 'pastBookings', 'stats', 'canUseFeatures', 'weeklyActivity')
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

    public function viewCollection(Request $request, ?int $id = null)
    {
        $id = $id ?: (int) ($request->input('id') ?: $request->getQueryString());
        if (!$id) abort(404);

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

        $isOwner = ($project->owner_id ?? 0) == ($researcher->id ?? 0);

        // Handle POST form actions
        if ($request->isMethod('post') && $isOwner) {
            $action = $request->input('form_action');

            if ($action === 'add_milestone') {
                $maxSort = DB::table('research_project_milestone')->where('project_id', $id)->max('sort_order') ?? 0;
                DB::table('research_project_milestone')->insert([
                    'project_id'  => $id,
                    'title'       => $request->input('milestone_title'),
                    'description' => $request->input('milestone_description'),
                    'due_date'    => $request->input('milestone_due_date') ?: null,
                    'status'      => $request->input('milestone_status', 'pending'),
                    'sort_order'  => $maxSort + 1,
                    'created_at'  => now(),
                ]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone added.');
            }

            if ($action === 'complete_milestone') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'completed', 'updated_at' => now()]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone completed.');
            }

            if ($action === 'delete_milestone') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone deleted.');
            }

            if ($action === 'add_resource') {
                DB::table('research_project_resource')->insert([
                    'project_id'    => $id,
                    'resource_type' => $request->input('resource_type', 'external_link'),
                    'title'         => $request->input('resource_title'),
                    'external_url'  => $request->input('external_url') ?: null,
                    'notes'         => $request->input('resource_notes') ?: null,
                    'added_at'      => now(),
                ]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Resource linked.');
            }

            if ($action === 'remove_resource') {
                DB::table('research_project_resource')
                    ->where('id', $request->input('resource_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Resource removed.');
            }

            if ($action === 'remove_collaborator') {
                DB::table('research_project_collaborator')
                    ->where('project_id', $id)
                    ->where('researcher_id', $request->input('collaborator_researcher_id'))
                    ->where('role', '!=', 'owner')
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Collaborator removed.');
            }
        }

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

        $reports = DB::table('research_report')
            ->where('project_id', $id)
            ->orderBy('updated_at', 'desc')
            ->get()->toArray();

        return view('research::research.view-project', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'project', 'collaborators', 'resources', 'milestones', 'activities', 'reports')
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

    public function bibliographies(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $bibliographies = DB::table('research_bibliography')
            ->where('researcher_id', $researcher->id)
            ->orderBy('name')
            ->get()->toArray();
        foreach ($bibliographies as $bib) {
            $bib->entry_count = DB::table('research_bibliography_entry')
                ->where('bibliography_id', $bib->id)->count();
        }

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
                $title = trim($request->input('title', ''));
                $objectId = (int) $request->input('object_id');

                // If object_id given but no title, resolve from DB
                if ($objectId && !$title) {
                    $obj = DB::table('information_object_i18n')
                        ->where('id', $objectId)->where('culture', 'en')->first();
                    $title = $obj->title ?? 'Untitled';
                }

                if ($title) {
                    $maxOrder = DB::table('research_bibliography_entry')
                        ->where('bibliography_id', $id)->max('sort_order') ?? 0;
                    DB::table('research_bibliography_entry')->insert([
                        'bibliography_id' => $id,
                        'object_id' => $objectId ?: null,
                        'title' => $title,
                        'authors' => $request->input('authors') ?: null,
                        'date' => $request->input('year') ?: null,
                        'container_title' => $request->input('publication') ?: null,
                        'volume' => $request->input('volume') ?: null,
                        'pages' => $request->input('pages') ?: null,
                        'doi' => $request->input('doi') ?: null,
                        'url' => $request->input('url') ?: null,
                        'entry_type' => $request->input('entry_type', 'book'),
                        'notes' => $request->input('notes') ?: null,
                        'sort_order' => $maxOrder + 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry added');
                }
                return redirect()->route('research.viewBibliography', $id)->with('error', 'Title is required');
            }

            if ($action === 'edit_entry') {
                $entryId = (int) $request->input('entry_id');
                DB::table('research_bibliography_entry')
                    ->where('id', $entryId)->where('bibliography_id', $id)
                    ->update([
                        'title' => $request->input('title'),
                        'authors' => $request->input('authors') ?: null,
                        'date' => $request->input('year') ?: null,
                        'container_title' => $request->input('publication') ?: null,
                        'volume' => $request->input('volume') ?: null,
                        'pages' => $request->input('pages') ?: null,
                        'doi' => $request->input('doi') ?: null,
                        'url' => $request->input('url') ?: null,
                        'entry_type' => $request->input('entry_type', 'book'),
                        'notes' => $request->input('notes') ?: null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry updated');
            }

            if ($action === 'update') {
                DB::table('research_bibliography')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description') ?: null,
                    'citation_style' => $request->input('citation_style', 'chicago'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Bibliography updated');
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

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $reportId = DB::table('research_report')->insertGetId([
                'researcher_id' => $researcher->id,
                'project_id' => $request->input('project_id') ?: null,
                'title' => $request->input('title'),
                'template_type' => $request->input('template_type', 'custom'),
                'description' => $request->input('description') ?: null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-create sections from template config in DB
            $template = DB::table('research_report_template')
                ->where('code', $request->input('template_type', 'custom'))
                ->first();

            if ($template && $template->sections_config) {
                $sectionConfigs = json_decode($template->sections_config, true) ?: [];
                foreach ($sectionConfigs as $i => $cfg) {
                    $parts = explode(':', $cfg, 2);
                    $type = $parts[0] ?? 'text';
                    $title = $parts[1] ?? ucfirst($type);
                    DB::table('research_report_section')->insert([
                        'report_id' => $reportId,
                        'section_type' => $type,
                        'title' => $title,
                        'sort_order' => $i,
                        'created_at' => now(),
                    ]);
                }
            }

            return redirect()->route('research.viewReport', $reportId)->with('success', 'Report created');
        }

        $query = DB::table('research_report as r')
            ->leftJoin('research_project as p', 'r.project_id', '=', 'p.id')
            ->where('r.researcher_id', $researcher->id);
        $currentStatus = $request->input('status');
        if ($currentStatus) $query->where('r.status', $currentStatus);

        $reports = $query
            ->select('r.*', 'p.title as project_title',
                DB::raw('(SELECT COUNT(*) FROM research_report_section WHERE report_id = r.id) as section_count'))
            ->orderBy('r.updated_at', 'desc')
            ->get()->toArray();

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')->orderBy('p.title')->get()->toArray();

        $templates = DB::table('research_report_template')->orderBy('is_system', 'desc')->orderBy('name')->get()->toArray();

        return view('research::research.reports', array_merge(
            $this->getSidebarData('reports'),
            compact('researcher', 'reports', 'currentStatus', 'projects', 'templates')
        ));
    }

    /**
     * Manage report templates (admin).
     */
    public function reportTemplates(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $code = \Illuminate\Support\Str::slug($request->input('name'), '_');
                $sections = array_filter(array_map('trim', explode("\n", $request->input('sections_raw', ''))));
                DB::table('research_report_template')->insert([
                    'name' => $request->input('name'),
                    'code' => $code,
                    'description' => $request->input('description') ?: null,
                    'sections_config' => json_encode($sections),
                    'is_system' => 0,
                    'created_at' => now(),
                ]);
                return redirect()->route('research.reportTemplates')->with('success', 'Template created.');
            }

            if ($action === 'update') {
                $id = (int) $request->input('template_id');
                $sections = array_filter(array_map('trim', explode("\n", $request->input('sections_raw', ''))));
                DB::table('research_report_template')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description') ?: null,
                    'sections_config' => json_encode($sections),
                ]);
                return redirect()->route('research.reportTemplates')->with('success', 'Template updated.');
            }

            if ($action === 'delete') {
                $id = (int) $request->input('template_id');
                $tpl = DB::table('research_report_template')->where('id', $id)->first();
                if ($tpl && !$tpl->is_system) {
                    DB::table('research_report_template')->where('id', $id)->delete();
                    return redirect()->route('research.reportTemplates')->with('success', 'Template deleted.');
                }
                return redirect()->route('research.reportTemplates')->with('error', 'System templates cannot be deleted.');
            }
        }

        $templates = DB::table('research_report_template')->orderBy('is_system', 'desc')->orderBy('name')->get()->toArray();

        return view('research::research.report-templates', array_merge(
            $this->getSidebarData('reports'),
            compact('templates')
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
                DB::table('research_report')->where('id', $id)->update(['status' => $request->input('status'), 'updated_at' => now()]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Status updated');
            }

            if ($action === 'move_section') {
                $sectionId = (int) $request->input('section_id');
                $direction = $request->input('direction');
                $section = DB::table('research_report_section')->where('id', $sectionId)->first();
                if ($section) {
                    $swap = DB::table('research_report_section')
                        ->where('report_id', $id)
                        ->where('sort_order', $direction === 'up' ? '<' : '>', $section->sort_order)
                        ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
                        ->first();
                    if ($swap) {
                        DB::table('research_report_section')->where('id', $section->id)->update(['sort_order' => $swap->sort_order]);
                        DB::table('research_report_section')->where('id', $swap->id)->update(['sort_order' => $section->sort_order]);
                    }
                }
                return redirect()->route('research.viewReport', $id);
            }

            if ($action === 'load_template') {
                $template = DB::table('research_report_template')
                    ->where('code', $request->input('template_code'))
                    ->first();
                if ($template && $template->sections_config) {
                    $maxOrder = DB::table('research_report_section')->where('report_id', $id)->max('sort_order') ?? -1;
                    $configs = json_decode($template->sections_config, true) ?: [];
                    foreach ($configs as $i => $cfg) {
                        $parts = explode(':', $cfg, 2);
                        DB::table('research_report_section')->insert([
                            'report_id' => $id, 'section_type' => $parts[0] ?? 'text',
                            'title' => $parts[1] ?? ucfirst($parts[0] ?? 'Section'),
                            'sort_order' => $maxOrder + 1 + $i, 'created_at' => now(),
                        ]);
                    }
                    return redirect()->route('research.viewReport', $id)->with('success', count($configs) . ' sections loaded from template');
                }
                return redirect()->route('research.viewReport', $id)->with('error', 'Template not found');
            }

            if ($action === 'add_multiple') {
                $types = $request->input('section_types', []);
                $maxOrder = DB::table('research_report_section')->where('report_id', $id)->max('sort_order') ?? -1;
                foreach ($types as $i => $type) {
                    DB::table('research_report_section')->insert([
                        'report_id' => $id, 'section_type' => $type,
                        'title' => ucwords(str_replace('_', ' ', $type)),
                        'sort_order' => $maxOrder + 1 + $i, 'created_at' => now(),
                    ]);
                }
                return redirect()->route('research.viewReport', $id)->with('success', count($types) . ' sections added');
            }

            if ($action === 'add_comment') {
                DB::table('research_discussion')->insert([
                    'workspace_id' => null, 'project_id' => null,
                    'parent_id' => null, 'researcher_id' => $researcher->id,
                    'subject' => 'Comment on section #' . $request->input('section_id'),
                    'content' => $request->input('comment_content'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return redirect()->route('research.viewReport', $id)->with('success', 'Comment added');
            }

            if ($action === 'update_header') {
                DB::table('research_report')->where('id', $id)->update([
                    'status' => $request->input('status'),
                    'updated_at' => now(),
                ]);
                return redirect()->route('research.viewReport', $id);
            }
        }

        // Export as printable PDF page
        if ($request->input('export') === 'pdf' || $request->input('export') === '1') {
            return view('research::research.report-pdf', compact('researcher', 'report'));
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

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $refNum = 'RPR-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $reqId = DB::table('research_reproduction_request')->insertGetId([
                'researcher_id' => $researcher->id,
                'reference_number' => $refNum,
                'purpose' => $request->input('purpose'),
                'intended_use' => $request->input('urgency', 'normal'),
                'publication_details' => $request->input('publication_details') ?: null,
                'delivery_method' => $request->input('delivery_method', 'email'),
                'notes' => $request->input('notes') ?: null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Add first item if provided
            $objectId = (int) $request->input('object_id');
            if ($objectId) {
                DB::table('research_reproduction_item')->insert([
                    'request_id' => $reqId,
                    'object_id' => $objectId,
                    'reproduction_type' => $request->input('reproduction_type', 'scan'),
                    'format' => $request->input('format', 'PDF'),
                    'special_instructions' => $request->input('specifications') ?: null,
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            }

            return redirect()->route('research.viewReproduction', $reqId)->with('success', 'Request created.' . ($objectId ? ' Item added.' : ' Add items to continue.'));
        }

        $query = DB::table('research_reproduction_request as r')
            ->where('r.researcher_id', $researcher->id);
        if ($request->input('status')) $query->where('r.status', $request->input('status'));

        $requests = $query
            ->select('r.*', DB::raw('(SELECT COUNT(*) FROM research_reproduction_item WHERE request_id = r.id) as item_count'))
            ->orderBy('r.created_at', 'desc')
            ->get()->toArray();

        return view('research::research.reproductions', array_merge(
            $this->getSidebarData('reproductions'),
            compact('researcher', 'requests')
        ));
    }

    /**
     * View a single reproduction request with its items.
     */
    public function viewReproduction(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $reproRequest = DB::table('research_reproduction_request')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$reproRequest) abort(404);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_item') {
                $objectId = (int) $request->input('object_id');
                if ($objectId) {
                    DB::table('research_reproduction_item')->insert([
                        'request_id' => $id,
                        'object_id' => $objectId,
                        'reproduction_type' => $request->input('reproduction_type', 'scan'),
                        'format' => $request->input('format', 'PDF'),
                        'quantity' => (int) ($request->input('quantity', 1)) ?: 1,
                        'special_instructions' => $request->input('special_instructions') ?: null,
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);
                    return redirect()->route('research.viewReproduction', $id)->with('success', 'Item added.');
                }
            }

            if ($action === 'remove_item') {
                DB::table('research_reproduction_item')
                    ->where('id', (int) $request->input('item_id'))
                    ->where('request_id', $id)->delete();
                return redirect()->route('research.viewReproduction', $id)->with('success', 'Item removed.');
            }

            if ($action === 'submit') {
                DB::table('research_reproduction_request')->where('id', $id)
                    ->update(['status' => 'submitted', 'updated_at' => now()]);
                return redirect()->route('research.viewReproduction', $id)->with('success', 'Request submitted for processing.');
            }

            if ($action === 'cancel') {
                DB::table('research_reproduction_request')->where('id', $id)
                    ->update(['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()]);
                return redirect()->route('research.reproductions')->with('success', 'Request cancelled.');
            }
        }

        $items = DB::table('research_reproduction_item as ri')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('ri.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'ri.object_id', '=', 's.object_id')
            ->where('ri.request_id', $id)
            ->select('ri.*', 'i18n.title as object_title', 's.slug as object_slug')
            ->orderBy('ri.created_at')
            ->get()->toArray();

        return view('research::research.view-reproduction', array_merge(
            $this->getSidebarData('reproductions'),
            compact('researcher', 'reproRequest', 'items')
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

        if ($request->isMethod('post') && $roomId) {
            $action = $request->input('form_action');
            $redir = redirect()->route('research.seats', ['room_id' => $roomId]);

            if ($action === 'create') {
                $maxSort = DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->max('sort_order') ?? 0;
                DB::table('research_reading_room_seat')->insert([
                    'reading_room_id' => $roomId,
                    'seat_number' => $request->input('seat_number'),
                    'seat_label' => $request->input('seat_label') ?: null,
                    'seat_type' => $request->input('seat_type', 'standard'),
                    'zone' => $request->input('zone') ?: null,
                    'has_power' => $request->has('has_power') ? 1 : 0,
                    'has_lamp' => $request->has('has_lamp') ? 1 : 0,
                    'has_computer' => $request->has('has_computer') ? 1 : 0,
                    'has_magnifier' => $request->has('has_magnifier') ? 1 : 0,
                    'notes' => $request->input('notes') ?: null,
                    'is_active' => 1,
                    'sort_order' => $maxSort + 1,
                    'created_at' => now(),
                ]);
                return $redir->with('success', 'Seat added.');
            }

            if ($action === 'update') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))->update([
                    'seat_number' => $request->input('seat_number'),
                    'seat_label' => $request->input('seat_label') ?: null,
                    'seat_type' => $request->input('seat_type', 'standard'),
                    'zone' => $request->input('zone') ?: null,
                    'has_power' => $request->has('has_power') ? 1 : 0,
                    'has_lamp' => $request->has('has_lamp') ? 1 : 0,
                    'has_computer' => $request->has('has_computer') ? 1 : 0,
                    'has_magnifier' => $request->has('has_magnifier') ? 1 : 0,
                    'notes' => $request->input('notes') ?: null,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Seat updated.');
            }

            if ($action === 'delete') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['is_active' => 0, 'updated_at' => now()]);
                return $redir->with('success', 'Seat deactivated.');
            }

            if ($action === 'release') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['status' => 'available', 'researcher_id' => null, 'updated_at' => now()]);
                return $redir->with('success', 'Seat released.');
            }

            if ($action === 'assign') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['status' => 'occupied', 'researcher_id' => (int) $request->input('researcher_id'), 'updated_at' => now()]);
                return $redir->with('success', 'Seat assigned.');
            }

            if ($action === 'bulk_create') {
                $pattern = trim($request->input('pattern', ''));
                $seatType = $request->input('seat_type', 'standard');
                $zone = $request->input('zone') ?: null;
                $created = 0;

                if (preg_match('/^([A-Za-z]*)(\d+)-\1?(\d+)$/', $pattern, $m)) {
                    $prefix = $m[1];
                    $start = (int) $m[2];
                    $end = (int) $m[3];
                    $maxSort = DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->max('sort_order') ?? 0;
                    for ($i = $start; $i <= $end; $i++) {
                        $seatNum = $prefix . $i;
                        $exists = DB::table('research_reading_room_seat')
                            ->where('reading_room_id', $roomId)->where('seat_number', $seatNum)->exists();
                        if (!$exists) {
                            DB::table('research_reading_room_seat')->insert([
                                'reading_room_id' => $roomId, 'seat_number' => $seatNum,
                                'seat_type' => $seatType, 'zone' => $zone,
                                'has_power' => 1, 'has_lamp' => 1, 'is_active' => 1,
                                'sort_order' => ++$maxSort, 'created_at' => now(),
                            ]);
                            $created++;
                        }
                    }
                }
                return $redir->with('success', "{$created} seats created.");
            }
        }

        $seats = $roomId ? DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->orderBy('sort_order')->get()->toArray() : [];

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

        if ($request->isMethod('post') && $roomId) {
            $action = $request->input('form_action');
            $redir = redirect()->route('research.equipment', ['room_id' => $roomId]);

            if ($action === 'create') {
                DB::table('research_equipment')->insert([
                    'reading_room_id' => $roomId,
                    'name' => $request->input('name'),
                    'code' => $request->input('code') ?: null,
                    'equipment_type' => $request->input('equipment_type'),
                    'location' => $request->input('location') ?: null,
                    'brand' => $request->input('brand') ?: null,
                    'model' => $request->input('model') ?: null,
                    'serial_number' => $request->input('serial_number') ?: null,
                    'max_booking_hours' => (int) ($request->input('max_booking_hours', 4)),
                    'description' => $request->input('description') ?: null,
                    'requires_training' => $request->has('requires_training') ? 1 : 0,
                    'is_available' => 1,
                    'condition_status' => 'good',
                    'created_at' => now(),
                ]);
                return $redir->with('success', 'Equipment added.');
            }

            if ($action === 'update') {
                DB::table('research_equipment')->where('id', (int) $request->input('equipment_id'))->update([
                    'name' => $request->input('name'),
                    'code' => $request->input('code') ?: null,
                    'equipment_type' => $request->input('equipment_type'),
                    'location' => $request->input('location') ?: null,
                    'brand' => $request->input('brand') ?: null,
                    'model' => $request->input('model') ?: null,
                    'serial_number' => $request->input('serial_number') ?: null,
                    'max_booking_hours' => (int) ($request->input('max_booking_hours', 4)),
                    'description' => $request->input('description') ?: null,
                    'requires_training' => $request->has('requires_training') ? 1 : 0,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Equipment updated.');
            }

            if ($action === 'maintenance') {
                $eqId = (int) $request->input('equipment_id');
                $eq = DB::table('research_equipment')->where('id', $eqId)->first();

                // Log to history
                DB::table('research_equipment_maintenance')->insert([
                    'equipment_id' => $eqId,
                    'description' => $request->input('maintenance_description'),
                    'condition_before' => $eq->condition_status ?? null,
                    'condition_after' => $request->input('new_condition', 'good'),
                    'next_maintenance_date' => $request->input('next_maintenance_date') ?: null,
                    'performed_by' => Auth::id(),
                    'performed_at' => now(),
                ]);

                // Update equipment record
                DB::table('research_equipment')->where('id', $eqId)->update([
                    'condition_status' => $request->input('new_condition', 'good'),
                    'last_maintenance_date' => date('Y-m-d'),
                    'next_maintenance_date' => $request->input('next_maintenance_date') ?: null,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Maintenance logged.');
            }
        }

        $equipment = $roomId ? DB::table('research_equipment')->where('reading_room_id', $roomId)->orderBy('name')->get()->toArray() : [];

        return view('research::research.equipment', array_merge(
            $this->getSidebarData('equipment'),
            compact('rooms', 'roomId', 'currentRoom', 'equipment')
        ));
    }

    public function equipmentHistory(int $id)
    {
        $logs = DB::table('research_equipment_maintenance as m')
            ->leftJoin('user as u', 'm.performed_by', '=', 'u.id')
            ->where('m.equipment_id', $id)
            ->select('m.*', 'u.username as performed_by_name')
            ->orderByDesc('m.performed_at')
            ->limit(50)
            ->get();

        return response()->json($logs);
    }

    public function retrievalQueue(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if (in_array($action, ['mark_in_transit', 'mark_delivered', 'mark_returned'])) {
                $newStatus = match($action) { 'mark_in_transit' => 'in_transit', 'mark_delivered' => 'delivered', 'mark_returned' => 'returned' };
                DB::table('research_material_request')
                    ->where('id', (int) $request->input('request_id'))
                    ->update(['status' => $newStatus, 'updated_at' => now()]);
                return redirect()->route('research.retrievalQueue', ['status' => $request->input('current_status')])->with('success', 'Status updated.');
            }

            if ($action === 'batch_update' && $request->input('new_status')) {
                $ids = $request->input('request_ids', []);
                if (!empty($ids)) {
                    DB::table('research_material_request')
                        ->whereIn('id', array_map('intval', $ids))
                        ->update(['status' => $request->input('new_status'), 'updated_at' => now()]);
                    return redirect()->route('research.retrievalQueue')->with('success', count($ids) . ' request(s) updated.');
                }
            }
        }

        $rooms = $this->service->getReadingRooms();
        $requests = DB::table('research_material_request as m')
            ->join('research_booking as b', 'm.booking_id', '=', 'b.id')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->select('m.*', 'b.booking_date', 'b.start_time', 'b.end_time', 'r.first_name', 'r.last_name', 'i18n.title as object_title')
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
                ->whereNull('check_out_time')
                ->orderBy('check_in_time')
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
                    'check_in_time' => date('H:i:s'),
                    'checked_in_by' => Auth::id(),
                ]);
                return redirect()->route('research.walkIn', ['room_id' => $roomId])
                    ->with('success', 'Walk-in visitor registered');
            }
            if ($action === 'checkout') {
                DB::table('research_walk_in_visitor')
                    ->where('id', (int) $request->input('visitor_id'))
                    ->update([
                        'check_out_time' => date('H:i:s'),
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

    public function resetPassword(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $newPassword = \Illuminate\Support\Str::random(12);
        DB::table('user')->where('id', $researcher->user_id)->update([
            'password_hash' => bcrypt($newPassword),
        ]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Password reset. New password: <strong>' . e($newPassword) . '</strong> — share this with the researcher securely.');
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
        DB::table('user')->where('id', $researcher->user_id)->update(['active' => 0]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher suspended');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Saved Searches)
    // =========================================================================

    public function storeSavedSearch(Request $request)
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) return response()->json(['error' => 'Not authenticated'], 401);
            return redirect()->route('login');
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) {
            if ($request->expectsJson()) return response()->json(['error' => 'Not a researcher'], 403);
            return redirect()->route('researcher.register');
        }

        $isAjax = $request->expectsJson() || $request->isJson();

        // Accept search_query OR search_params (from GLAM browse AJAX)
        $searchQuery = $request->input('search_query')
            ?: $request->input('search_params')
            ?: $request->input('query')
            ?: '';

        if (!$searchQuery) {
            if ($isAjax) return response()->json(['success' => false, 'error' => 'Search query is required'], 422);
            return redirect()->route('research.savedSearches')->with('error', 'Search query is required');
        }

        $this->service->saveSearch($researcher->id, [
            'name' => $request->input('name'),
            'search_query' => $searchQuery,
        ]);

        if ($isAjax) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('research.savedSearches')->with('success', 'Search saved');
    }

    /**
     * Snapshot current search results for diff comparison.
     */
    public function searchSnapshot(Request $request, int $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Not authenticated'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $search = DB::table('research_saved_search')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$search) return response()->json(['error' => 'Search not found'], 404);

        // Run the search query against DB to get current result IDs
        $keyword = $this->extractSearchKeyword($search->search_query);
        $results = [];
        if ($keyword) {
            $results = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })
                ->where('io.id', '!=', 1)
                ->where(function ($q) use ($keyword) {
                    $q->where('i18n.title', 'LIKE', "%{$keyword}%")
                      ->orWhere('i18n.scope_and_content', 'LIKE', "%{$keyword}%")
                      ->orWhere('io.identifier', 'LIKE', "%{$keyword}%");
                })
                ->pluck('io.id')->toArray();
        }

        DB::table('research_saved_search')->where('id', $id)->update([
            'result_snapshot_json' => json_encode($results),
            'last_result_count' => count($results),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'count' => count($results)]);
    }

    /**
     * Diff current search results against last snapshot.
     */
    public function searchDiff(Request $request, int $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Not authenticated'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $search = DB::table('research_saved_search')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$search) return response()->json(['error' => 'Search not found'], 404);

        $previousIds = json_decode($search->result_snapshot_json ?? '[]', true) ?: [];
        if (empty($previousIds)) {
            return response()->json(['error' => 'No previous snapshot. Take a snapshot first.']);
        }

        // Run current search
        $query = $search->search_query;
        $currentIds = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('i18n.title', 'LIKE', "%{$query}%")
                  ->orWhere('io.identifier', 'LIKE', "%{$query}%");
            })
            ->pluck('io.id')->toArray();

        $added = array_values(array_diff($currentIds, $previousIds));
        $removed = array_values(array_diff($previousIds, $currentIds));
        $unchanged = count(array_intersect($currentIds, $previousIds));

        return response()->json([
            'previous_count' => count($previousIds),
            'current_count' => count($currentIds),
            'unchanged_count' => $unchanged,
            'added' => $added,
            'removed' => $removed,
        ]);
    }

    /**
     * Extract the search keyword from a saved search query string.
     * Handles both plain keywords ("ai") and URL params ("query=ai&title=&...").
     */
    private function extractSearchKeyword(string $searchQuery): string
    {
        if (str_contains($searchQuery, '=')) {
            parse_str($searchQuery, $params);
            return trim($params['query'] ?? $params['sq0'] ?? $params['subquery'] ?? '');
        }
        return trim($searchQuery);
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

    /**
     * View a single team workspace with members and resources.
     */
    public function viewWorkspace(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $workspace = DB::table('research_workspace')->where('id', $id)->first();
        if (!$workspace) abort(404);

        // Check access: owner or member
        $isMember = $workspace->owner_id === $researcher->id
            || DB::table('research_workspace_member')
                ->where('workspace_id', $id)
                ->where('researcher_id', $researcher->id)
                ->where('status', 'accepted')
                ->exists();

        if (!$isMember && $workspace->visibility === 'private') {
            abort(403, 'You do not have access to this workspace.');
        }

        $members = DB::table('research_workspace_member as m')
            ->join('research_researcher as r', 'r.id', '=', 'm.researcher_id')
            ->where('m.workspace_id', $id)
            ->where('m.researcher_id', '!=', $workspace->owner_id)
            ->select('m.*', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as name"), 'r.email', 'r.institution')
            ->orderBy('m.role')
            ->get()->toArray();

        // Add owner as first member
        $owner = DB::table('research_researcher')->where('id', $workspace->owner_id)
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email', 'institution')
            ->first();

        $resources = DB::table('research_workspace_resource')
            ->where('workspace_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        $myRole = $workspace->owner_id === $researcher->id ? 'owner' : (
            DB::table('research_workspace_member')
                ->where('workspace_id', $id)
                ->where('researcher_id', $researcher->id)
                ->value('role') ?? 'viewer'
        );

        // Handle POST actions
        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $redir = redirect()->route('research.viewWorkspace', $id);

            if ($action === 'edit_workspace' && in_array($myRole, ['owner', 'admin'])) {
                DB::table('research_workspace')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'visibility' => $request->input('visibility', 'private'),
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Workspace updated.');
            }

            if ($action === 'delete_workspace' && $myRole === 'owner') {
                DB::table('research_workspace_resource')->where('workspace_id', $id)->delete();
                DB::table('research_workspace_member')->where('workspace_id', $id)->delete();
                DB::table('research_discussion')->where('workspace_id', $id)->delete();
                DB::table('research_workspace')->where('id', $id)->delete();
                return redirect()->route('research.workspaces')->with('success', 'Workspace deleted.');
            }

            if ($action === 'invite' && in_array($myRole, ['owner', 'admin'])) {
                $inviteEmail = $request->input('email');
                $inviteResearcher = DB::table('research_researcher')->where('email', $inviteEmail)->first();
                if ($inviteResearcher) {
                    $exists = DB::table('research_workspace_member')
                        ->where('workspace_id', $id)->where('researcher_id', $inviteResearcher->id)->exists();
                    if (!$exists) {
                        DB::table('research_workspace_member')->insert([
                            'workspace_id' => $id, 'researcher_id' => $inviteResearcher->id,
                            'role' => $request->input('role', 'viewer'), 'invited_by' => $researcher->id,
                            'status' => 'accepted', 'invited_at' => now(), 'accepted_at' => now(),
                        ]);
                        return $redir->with('success', 'Member added.');
                    }
                    return $redir->with('error', 'Already a member.');
                }
                return $redir->with('error', 'Researcher not found with that email.');
            }

            if ($action === 'change_role' && in_array($myRole, ['owner', 'admin'])) {
                DB::table('research_workspace_member')
                    ->where('workspace_id', $id)->where('id', $request->input('member_id'))
                    ->update(['role' => $request->input('role')]);
                return $redir->with('success', 'Role updated.');
            }

            if ($action === 'remove_member' && in_array($myRole, ['owner', 'admin'])) {
                DB::table('research_workspace_member')
                    ->where('workspace_id', $id)->where('id', $request->input('member_id'))->delete();
                return $redir->with('success', 'Member removed.');
            }

            if ($action === 'add_resource' && in_array($myRole, ['owner', 'admin', 'editor'])) {
                DB::table('research_workspace_resource')->insert([
                    'workspace_id' => $id, 'resource_type' => $request->input('resource_type', 'link'),
                    'resource_id' => $request->input('resource_id') ?: null,
                    'external_url' => $request->input('external_url') ?: null,
                    'title' => $request->input('title'), 'description' => $request->input('notes'),
                    'added_by' => $researcher->id, 'added_at' => now(),
                ]);
                return $redir->with('success', 'Resource added.');
            }

            if ($action === 'edit_resource' && in_array($myRole, ['owner', 'admin', 'editor'])) {
                DB::table('research_workspace_resource')
                    ->where('workspace_id', $id)->where('id', $request->input('resource_id'))
                    ->update([
                        'title' => $request->input('title'),
                        'resource_type' => $request->input('resource_type', 'link'),
                        'external_url' => $request->input('external_url') ?: null,
                        'description' => $request->input('notes'),
                    ]);
                return $redir->with('success', 'Resource updated.');
            }

            if ($action === 'remove_resource' && in_array($myRole, ['owner', 'admin', 'editor'])) {
                DB::table('research_workspace_resource')
                    ->where('workspace_id', $id)->where('id', $request->input('resource_id'))->delete();
                return $redir->with('success', 'Resource removed.');
            }

            if ($action === 'create_discussion') {
                DB::table('research_discussion')->insert([
                    'workspace_id' => $id, 'researcher_id' => $researcher->id,
                    'subject' => $request->input('title'), 'content' => $request->input('content'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return $redir->with('success', 'Discussion created.');
            }

            if ($action === 'edit_discussion') {
                DB::table('research_discussion')
                    ->where('id', $request->input('discussion_id'))->where('workspace_id', $id)
                    ->update(['subject' => $request->input('title'), 'content' => $request->input('content'), 'updated_at' => now()]);
                return $redir->with('success', 'Discussion updated.');
            }

            if ($action === 'delete_discussion') {
                DB::table('research_discussion')
                    ->where('id', $request->input('discussion_id'))->where('workspace_id', $id)->delete();
                return $redir->with('success', 'Discussion deleted.');
            }
        }

        // Load discussions
        $discussions = DB::table('research_discussion as d')
            ->leftJoin('research_researcher as r', 'r.id', '=', 'd.researcher_id')
            ->where('d.workspace_id', $id)->whereNull('d.parent_id')
            ->select('d.*', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as author_name"))
            ->orderByDesc('d.is_pinned')->orderByDesc('d.created_at')
            ->get();

        // Count replies per discussion
        foreach ($discussions as $disc) {
            $disc->reply_count = DB::table('research_discussion')
                ->where('parent_id', $disc->id)->count();
        }
        $discussions = $discussions->toArray();

        // Shared collections
        $sharedCollections = [];
        try {
            $collectionIds = DB::table('research_workspace_resource')
                ->where('workspace_id', $id)->where('resource_type', 'collection')
                ->pluck('resource_id')->toArray();
            if (!empty($collectionIds)) {
                $sharedCollections = DB::table('research_collection as c')
                    ->leftJoin('research_researcher as r', 'c.researcher_id', '=', 'r.id')
                    ->leftJoin(DB::raw('(SELECT collection_id, COUNT(*) as cnt FROM research_collection_item GROUP BY collection_id) ci'), 'c.id', '=', 'ci.collection_id')
                    ->whereIn('c.id', $collectionIds)
                    ->select('c.id', 'c.name', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as owner_name"), DB::raw('COALESCE(ci.cnt, 0) as item_count'))
                    ->orderBy('c.name')->get()->toArray();
            }
        } catch (\Exception $e) {}

        return view('research::research.view-workspace', array_merge(
            $this->getSidebarData('workspaces'),
            compact('workspace', 'owner', 'members', 'resources', 'myRole', 'researcher', 'discussions', 'sharedCollections')
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

    /**
     * AJAX: Researcher autocomplete (returns JSON).
     */
    public function researcherAutocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $results = DB::table('research_researcher')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email')
            ->limit(20)
            ->get();

        return response()->json($results);
    }

    /**
     * AJAX: Target autocomplete for ODRL policies (returns JSON).
     */
    public function targetAutocomplete(Request $request)
    {
        $type = $request->get('type', '');
        $query = $request->get('query', '');
        $culture = app()->getLocale();

        $map = [
            'archival_description' => [
                'query' => fn () => DB::table('information_object')
                    ->join('information_object_i18n', function ($j) use ($culture) {
                        $j->on('information_object.id', '=', 'information_object_i18n.id')
                          ->where('information_object_i18n.culture', '=', $culture);
                    })
                    ->where('information_object_i18n.title', 'LIKE', "%{$query}%")
                    ->select('information_object.id', 'information_object_i18n.title as name')
                    ->limit(20)->get(),
            ],
            'collection' => [
                'query' => fn () => DB::table('research_collection')
                    ->where('name', 'LIKE', "%{$query}%")
                    ->select('id', 'name')
                    ->limit(20)->get(),
            ],
            'project' => [
                'query' => fn () => DB::table('research_project')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'snapshot' => [
                'query' => fn () => DB::table('research_snapshot')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'annotation' => [
                'query' => fn () => DB::table('research_annotation')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'assertion' => [
                'query' => fn () => DB::table('research_assertion')
                    ->where('assertion_type', 'LIKE', "%{$query}%")
                    ->select('id', 'assertion_type as name')
                    ->limit(20)->get(),
            ],
        ];

        if (!isset($map[$type])) {
            return response()->json([]);
        }

        try {
            return response()->json($map[$type]['query']());
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

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

            if ($formAction === 'update') {
                $policyId = (int) $request->input('policy_id');
                $constraintsJson = $request->input('constraints_json');
                if ($constraintsJson) {
                    $decoded = json_decode($constraintsJson, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        return redirect('/research/odrlPolicies')->with('error', 'Invalid JSON in constraints.');
                    }
                }

                DB::table('research_rights_policy')
                    ->where('id', $policyId)
                    ->update([
                        'target_type'      => $request->input('target_type'),
                        'target_id'        => (int) $request->input('target_id'),
                        'policy_type'      => $request->input('policy_type'),
                        'action_type'      => $request->input('action_type'),
                        'constraints_json' => $constraintsJson ?: null,
                        'updated_at'       => now(),
                    ]);
                return redirect('/research/odrlPolicies')->with('success', 'Policy updated.');
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

        // Resolve target names and researcher names for display
        $culture = app()->getLocale();
        $targetNameCache = [];
        $researcherNameCache = [];

        foreach ($policies['items'] as $p) {
            // Resolve target name
            $cacheKey = $p->target_type . ':' . $p->target_id;
            if (!isset($targetNameCache[$cacheKey])) {
                $targetNameCache[$cacheKey] = $this->resolveTargetName($p->target_type, $p->target_id, $culture);
            }
            $p->target_name = $targetNameCache[$cacheKey];

            // Resolve researcher names in constraints
            $p->resolved_constraints = [];
            if (!empty($p->constraints_json)) {
                $constraints = json_decode($p->constraints_json, true);
                if (is_array($constraints)) {
                    foreach ($constraints as $ck => $cv) {
                        if ($ck === 'researcher_ids' && is_array($cv)) {
                            $names = [];
                            foreach ($cv as $rid) {
                                if (!isset($researcherNameCache[$rid])) {
                                    $r = DB::table('research_researcher')->where('id', $rid)
                                        ->select(DB::raw("CONCAT(first_name, ' ', last_name) as name"))->first();
                                    $researcherNameCache[$rid] = $r->name ?? "#{$rid}";
                                }
                                $names[] = $researcherNameCache[$rid];
                            }
                            $p->resolved_constraints['Researchers'] = implode(', ', $names);
                        } elseif ($ck === 'date_from') {
                            $p->resolved_constraints['From'] = $cv;
                        } elseif ($ck === 'date_to') {
                            $p->resolved_constraints['Until'] = $cv;
                        } elseif ($ck === 'max_uses') {
                            $p->resolved_constraints['Max uses'] = $cv;
                        } else {
                            $p->resolved_constraints[$ck] = is_array($cv) ? implode(', ', $cv) : $cv;
                        }
                    }
                }
            }
        }

        return view('research::research.odrl-policies', array_merge(
            $this->getSidebarData('odrlPolicies'),
            compact('policies')
        ));
    }

    /**
     * Resolve a target type + ID to a human-readable name.
     */
    private function resolveTargetName(string $type, int $id, string $culture = 'en'): string
    {
        try {
            return match ($type) {
                'archival_description' => DB::table('information_object_i18n')
                    ->where('id', $id)->where('culture', $culture)->value('title') ?? "AD #{$id}",
                'collection' => DB::table('research_collection')->where('id', $id)->value('name') ?? "Collection #{$id}",
                'project' => DB::table('research_project')->where('id', $id)->value('title') ?? "Project #{$id}",
                'snapshot' => DB::table('research_snapshot')->where('id', $id)->value('title') ?? "Snapshot #{$id}",
                'annotation' => DB::table('research_annotation')->where('id', $id)->value('title') ?? "Annotation #{$id}",
                'assertion' => DB::table('research_assertion')->where('id', $id)->value('assertion_type') ?? "Assertion #{$id}",
                default => "#{$id}",
            };
        } catch (\Exception $e) {
            return "#{$id}";
        }
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

    // =========================================================================
    // PROJECT ANALYSIS TOOLS
    // =========================================================================

    /**
     * Helper: load project + researcher with access check.
     */
    protected function loadProjectContext(int $id): array
    {
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        return [$project, $researcher];
    }

    public function knowledgeGraph(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        // API endpoint for graph data
        if ($request->wantsJson()) {
            $assertions = DB::table('research_assertion as a')
                ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $id)
                ->select('a.*', DB::raw('COUNT(e.id) as evidence_count'))
                ->groupBy('a.id')
                ->get();

            $nodes = [];
            $edges = [];
            foreach ($assertions as $a) {
                $nodes[] = ['id' => $a->id, 'label' => $a->subject_label ?? '', 'type' => $a->assertion_type ?? '', 'status' => $a->status ?? ''];
                if ($a->object_label ?? null) {
                    $edges[] = ['source' => $a->id, 'target' => $a->object_label, 'label' => $a->predicate ?? ''];
                }
            }
            return response()->json(['nodes' => $nodes, 'edges' => $edges]);
        }

        return view('research::research.knowledge-graph', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    public function assertions(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $typeFilter = $request->input('type');
        $statusFilter = $request->input('status');

        $query = DB::table('research_assertion as a')
            ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
            ->where('a.project_id', $id)
            ->select('a.*', DB::raw('COUNT(e.id) as evidence_count'))
            ->groupBy('a.id');

        if ($typeFilter) $query->where('a.assertion_type', $typeFilter);
        if ($statusFilter) $query->where('a.status', $statusFilter);

        $assertions = $query->orderBy('a.created_at', 'desc')->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            DB::table('research_assertion')->insert([
                'project_id'     => $id,
                'researcher_id'  => $researcher->id,
                'assertion_type' => $request->input('assertion_type', 'biographical'),
                'subject_type'   => 'text',
                'subject_id'     => 0,
                'subject_label'  => $request->input('subject'),
                'predicate'      => $request->input('predicate'),
                'object_value'   => $request->input('object'),
                'object_label'   => $request->input('object'),
                'confidence'     => $request->input('confidence', 0.5),
                'status'         => 'proposed',
                'created_at'     => now(),
            ]);
            return redirect()->route('research.assertions', $id)->with('success', 'Assertion created.');
        }

        return view('research::research.assertions', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'assertions', 'typeFilter', 'statusFilter')
        ));
    }

    public function hypotheses(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $hypotheses = DB::table('research_hypothesis')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            DB::table('research_hypothesis')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'statement'     => $request->input('statement', $request->input('title', '')),
                'tags'          => $request->input('tags'),
                'status'        => 'proposed',
                'created_at'    => now(),
            ]);
            return redirect()->route('research.hypotheses', $id)->with('success', 'Hypothesis created.');
        }

        return view('research::research.hypotheses', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'hypotheses')
        ));
    }

    public function extractionJobs(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        // Handle POST actions
        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $params = [];
                if ($request->input('language')) $params['language'] = $request->input('language');
                if ($request->input('model')) $params['model'] = $request->input('model');

                DB::table('research_extraction_job')->insert([
                    'project_id'      => $id,
                    'collection_id'   => $request->input('collection_id'),
                    'researcher_id'   => $researcher->id,
                    'extraction_type' => $request->input('extraction_type', 'ner'),
                    'parameters_json' => !empty($params) ? json_encode($params) : null,
                    'status'          => 'queued',
                    'total_items'     => 0,
                    'processed_items' => 0,
                    'created_at'      => now(),
                ]);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Extraction job created.');
            }

            if ($action === 'cancel' && $request->input('job_id')) {
                DB::table('research_extraction_job')
                    ->where('id', $request->input('job_id'))
                    ->where('project_id', $id)
                    ->whereIn('status', ['queued', 'running'])
                    ->update(['status' => 'cancelled']);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Job cancelled.');
            }

            if ($action === 'retry' && $request->input('job_id')) {
                DB::table('research_extraction_job')
                    ->where('id', $request->input('job_id'))
                    ->where('project_id', $id)
                    ->where('status', 'failed')
                    ->update(['status' => 'queued']);
                return redirect()->route('research.extractionJobs', $id)->with('success', 'Job re-queued.');
            }
        }

        $statusFilter = $request->input('status');
        $query = DB::table('research_extraction_job')
            ->where('project_id', $id);
        if ($statusFilter) $query->where('status', $statusFilter);

        $jobs = $query->orderBy('created_at', 'desc')->get()->toArray();

        return view('research::research.extraction-jobs', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'jobs')
        ));
    }

    public function snapshots(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $snapshots = DB::table('research_snapshot')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $snapshotId = DB::table('research_snapshot')->insertGetId([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'title'         => $request->input('title', 'Snapshot ' . date('Y-m-d H:i')),
                'description'   => $request->input('description'),
                'status'        => 'active',
                'created_at'    => now(),
            ]);
            return redirect()->route('research.snapshots', $id)->with('success', 'Snapshot created.');
        }

        return view('research::research.snapshots', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'snapshots')
        ));
    }

    public function viewSnapshot(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $snapshot = DB::table('research_snapshot')->where('id', $id)->first();
        if (!$snapshot) abort(404, 'Snapshot not found');

        $project = DB::table('research_project')->where('id', $snapshot->project_id)->first();
        if (!$project) abort(404);

        $items = DB::table('research_snapshot_item as si')
            ->leftJoin('information_object_i18n as ioi18n', function ($j) {
                $j->on('si.object_id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
            })
            ->where('si.snapshot_id', $id)
            ->select('si.*', 'ioi18n.title as object_title')
            ->orderBy('si.sort_order')
            ->get()->toArray();

        return view('research::research.view-snapshot', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'snapshot', 'items')
        ));
    }

    public function assertionBatchReview(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $assertions = DB::table('research_assertion')
            ->where('project_id', $id)
            ->where('status', 'proposed')
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'batch_update') {
            $ids = $request->input('assertion_ids', []);
            $newStatus = $request->input('new_status', 'verified');
            if (!empty($ids)) {
                DB::table('research_assertion')
                    ->whereIn('id', $ids)
                    ->where('project_id', $id)
                    ->update(['status' => $newStatus, 'updated_at' => now()]);
            }
            return redirect()->route('research.assertionBatchReview', $id)->with('success', count($ids) . ' assertions updated.');
        }

        return view('research::research.assertion-batch-review', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'assertions')
        ));
    }

    // =========================================================================
    // PROJECT VISUALIZATION
    // =========================================================================

    public function timelineBuilder(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $events = DB::table('research_timeline_event')
                ->where('project_id', $id)
                ->orderBy('date_start')
                ->get();
            return response()->json($events);
        }

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'update_event' && $request->input('event_id')) {
                $update = ['date_start' => $request->input('date_start')];
                if ($request->has('date_end')) $update['date_end'] = $request->input('date_end') ?: null;
                if ($request->has('label')) $update['label'] = $request->input('label');
                if ($request->has('description')) $update['description'] = $request->input('description');
                if ($request->has('date_type')) $update['date_type'] = $request->input('date_type');
                DB::table('research_timeline_event')
                    ->where('id', $request->input('event_id'))
                    ->where('project_id', $id)
                    ->update($update);
                return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event updated.');
            }

            if ($action === 'delete_event' && $request->input('event_id')) {
                DB::table('research_timeline_event')
                    ->where('id', $request->input('event_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event deleted.');
            }

            if ($action === 'auto_populate' && $request->input('collection_id')) {
                // Auto-populate from collection items that have dates
                $collectionId = (int) $request->input('collection_id');
                $items = DB::table('information_object as io')
                    ->join('information_object_i18n as ioi18n', function ($j) {
                        $j->on('io.id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
                    })
                    ->join('event', 'event.object_id', '=', 'io.id')
                    ->where('io.parent_id', $collectionId)
                    ->whereNotNull('event.start_date')
                    ->select('ioi18n.title', 'event.start_date', 'event.end_date', 'event.type_id')
                    ->get();

                $count = 0;
                foreach ($items as $item) {
                    if (!$item->start_date) continue;
                    DB::table('research_timeline_event')->insert([
                        'project_id'    => $id,
                        'researcher_id' => $researcher->id,
                        'label'         => $item->title ?: 'Untitled',
                        'date_start'    => $item->start_date,
                        'date_end'      => $item->end_date ?: null,
                        'date_type'     => 'event',
                        'created_at'    => now(),
                    ]);
                    $count++;
                }
                return redirect()->route('research.timelineBuilder', $id)->with('success', "Added {$count} events from collection.");
            }

            // Default: create new event (only if we have required data)
            $label = $request->input('title', $request->input('label', ''));
            $dateStart = $request->input('event_date', $request->input('date_start'));
            if (!$label || !$dateStart) {
                return redirect()->route('research.timelineBuilder', $id)->with('error', 'Label and start date are required.');
            }

            DB::table('research_timeline_event')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'label'         => $label,
                'description'   => $request->input('description'),
                'date_start'    => $dateStart,
                'date_end'      => $request->input('date_end') ?: null,
                'date_type'     => $request->input('event_type', $request->input('date_type', 'event')),
                'created_at'    => now(),
            ]);
            return redirect()->route('research.timelineBuilder', $id)->with('success', 'Event added.');
        }

        $events = DB::table('research_timeline_event')
            ->where('project_id', $id)
            ->orderBy('date_start')
            ->get()->toArray();

        return view('research::research.timeline-builder', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'events')
        ));
    }

    public function mapBuilder(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $points = DB::table('research_map_point')
                ->where('project_id', $id)
                ->get();
            return response()->json($points);
        }

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'update_point' && $request->input('point_id')) {
                DB::table('research_map_point')
                    ->where('id', $request->input('point_id'))
                    ->where('project_id', $id)
                    ->update([
                        'label'       => $request->input('label'),
                        'place_name'  => $request->input('place_name'),
                        'latitude'    => $request->input('latitude'),
                        'longitude'   => $request->input('longitude'),
                        'description' => $request->input('description'),
                    ]);
                return redirect()->route('research.mapBuilder', $id)->with('success', 'Point updated.');
            }

            if ($action === 'delete_point' && $request->input('point_id')) {
                DB::table('research_map_point')
                    ->where('id', $request->input('point_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.mapBuilder', $id)->with('success', 'Point deleted.');
            }

            // Default: create
            DB::table('research_map_point')->insert([
                'project_id'    => $id,
                'researcher_id' => $researcher->id,
                'label'         => $request->input('label', ''),
                'description'   => $request->input('description'),
                'latitude'      => $request->input('latitude'),
                'longitude'     => $request->input('longitude'),
                'place_name'    => $request->input('place_name'),
                'created_at'    => now(),
            ]);
            return redirect()->route('research.mapBuilder', $id)->with('success', 'Point added.');
        }

        $points = DB::table('research_map_point')
            ->where('project_id', $id)
            ->get()->toArray();

        return view('research::research.map-builder', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'points')
        ));
    }

    public function networkGraph(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->wantsJson()) {
            $assertions = DB::table('research_assertion')
                ->where('project_id', $id)
                ->get();
            $nodes = [];
            $edges = [];
            $nodeMap = [];
            foreach ($assertions as $a) {
                if ($a->subject_label && !isset($nodeMap[$a->subject_label])) {
                    $nodeMap[$a->subject_label] = count($nodes);
                    $nodes[] = ['id' => $a->subject_label, 'label' => $a->subject_label, 'group' => $a->assertion_type ?? 'default'];
                }
                if ($a->object_label && !isset($nodeMap[$a->object_label])) {
                    $nodeMap[$a->object_label] = count($nodes);
                    $nodes[] = ['id' => $a->object_label, 'label' => $a->object_label, 'group' => $a->assertion_type ?? 'default'];
                }
                if ($a->subject_label && $a->object_label) {
                    $edges[] = ['from' => $a->subject_label, 'to' => $a->object_label, 'label' => $a->predicate ?? ''];
                }
            }
            return response()->json(['nodes' => $nodes, 'edges' => $edges]);
        }

        return view('research::research.network-graph', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    // =========================================================================
    // PROJECT RESEARCH OUTPUT
    // =========================================================================

    public function roCrate(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        $resources = DB::table('research_project_resource')
            ->where('project_id', $id)
            ->get()->toArray();

        // Build RO-Crate manifest
        $manifest = [
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph' => [
                ['@type' => 'CreativeWork', '@id' => 'ro-crate-metadata.json', 'conformsTo' => ['@id' => 'https://w3id.org/ro/crate/1.1']],
                [
                    '@type' => 'Dataset',
                    '@id' => './',
                    'name' => $project->title,
                    'description' => $project->description ?? '',
                    'dateCreated' => $project->created_at ?? '',
                    'author' => array_map(fn($c) => ['@type' => 'Person', 'name' => $c->first_name . ' ' . $c->last_name], $collaborators),
                ],
            ],
        ];

        return view('research::research.ro-crate', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'manifest', 'collaborators', 'resources')
        ));
    }

    public function reproducibilityPack(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $milestones = DB::table('research_project_milestone')->where('project_id', $id)->orderBy('sort_order')->get()->toArray();
        $resources = DB::table('research_project_resource')->where('project_id', $id)->get()->toArray();
        $assertions = DB::table('research_assertion')->where('project_id', $id)->get()->toArray();
        $hypotheses = DB::table('research_hypothesis')->where('project_id', $id)->get()->toArray();
        $snapshots = DB::table('research_snapshot')->where('project_id', $id)->get()->toArray();
        $extractionJobs = DB::table('research_extraction_job')->where('project_id', $id)->get()->toArray();

        $searchQueries = [];
        try {
            $searchQueries = DB::table('research_saved_search')->where('researcher_id', $researcher->id)->get()->toArray();
        } catch (\Exception $e) {}

        // JSON download
        if ($request->input('format') === 'json') {
            return response()->json([
                'project' => $project,
                'milestones' => $milestones,
                'resources' => $resources,
                'assertions' => $assertions,
                'hypotheses' => $hypotheses,
                'snapshots' => $snapshots,
                'extraction_jobs' => $extractionJobs,
                'search_queries' => $searchQueries,
                'integrity_hash' => hash('sha256', json_encode([$project->id, count($assertions), count($snapshots), count($milestones)])),
            ]);
        }

        return view('research::research.reproducibility-pack', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'milestones', 'resources', 'assertions', 'hypotheses', 'snapshots', 'extractionJobs', 'searchQueries')
        ));
    }

    public function mintDoi(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('r.first_name', 'r.last_name')
            ->get();

        $creatorsString = $collaborators->map(fn($c) => $c->first_name . ' ' . $c->last_name)->implode(', ');
        $currentDoi = $project->doi ?? null;
        $doiMintedAt = $project->doi_minted_at ?? null;

        if ($request->isMethod('post')) {
            // DOI minting would integrate with DataCite API
            $doi = '10.5281/heratio.' . $project->id . '.' . time();
            DB::table('research_project')->where('id', $id)->update([
                'doi' => $doi,
                'doi_minted_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'doi' => $doi]);
            }
            return redirect()->route('research.mintDoi', $id)->with('success', 'DOI minted: ' . $doi);
        }

        return view('research::research.mint-doi', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'creatorsString', 'currentDoi', 'doiMintedAt')
        ));
    }

    public function ethicsMilestones(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'create') {
                $maxSort = DB::table('research_project_milestone')->where('project_id', $id)->max('sort_order') ?? 0;
                DB::table('research_project_milestone')->insert([
                    'project_id'  => $id,
                    'title'       => $request->input('title'),
                    'description' => $request->input('description'),
                    'status'      => 'pending',
                    'sort_order'  => $maxSort + 1,
                    'created_at'  => now(),
                ]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone added.');
            }

            if ($action === 'edit') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update([
                        'title'       => $request->input('title'),
                        'description' => $request->input('description'),
                        'due_date'    => $request->input('due_date') ?: null,
                    ]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone updated.');
            }

            if ($action === 'approve') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'approved']);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone approved.');
            }

            if ($action === 'reject') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'rejected']);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone rejected.');
            }

            if ($action === 'complete') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => Auth::id()]);
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone completed.');
            }

            if ($action === 'delete') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.ethicsMilestones', $id)->with('success', 'Milestone deleted.');
            }
        }

        $milestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        return view('research::research.ethics-milestones', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'milestones')
        ));
    }

    public function complianceDashboard(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $ethicsMilestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        // Compute ethics status
        $ethicsStatus = 'not_started';
        if (!empty($ethicsMilestones)) {
            $statuses = array_column($ethicsMilestones, 'status');
            if (in_array('rejected', $statuses)) $ethicsStatus = 'rejected';
            elseif (in_array('pending', $statuses)) $ethicsStatus = 'pending';
            elseif (count(array_filter($statuses, fn($s) => in_array($s, ['approved', 'completed']))) === count($statuses)) $ethicsStatus = 'approved';
            else $ethicsStatus = 'pending';
        }

        $odrlPolicies = DB::table('research_rights_policy')
            ->where('target_type', 'project')
            ->where('target_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();
        $odrlPolicyCount = count($odrlPolicies);

        // Sensitivity breakdown from linked resources
        $sensitivityBreakdown = [];
        $sensitivitySummary = ['max_level' => 'none'];
        try {
            $resourceObjectIds = DB::table('research_project_resource')
                ->where('project_id', $id)
                ->whereNotNull('object_id')
                ->pluck('object_id');
            if ($resourceObjectIds->isNotEmpty()) {
                $classifications = DB::table('object_security_classification')
                    ->whereIn('object_id', $resourceObjectIds)
                    ->get();
                foreach ($classifications as $c) {
                    $level = $c->classification_level ?? 'unclassified';
                    $sensitivityBreakdown[$level] = ($sensitivityBreakdown[$level] ?? 0) + 1;
                }
                $levelOrder = ['top_secret' => 4, 'secret' => 3, 'confidential' => 2, 'unclassified' => 1, 'none' => 0];
                $maxLevel = 'none';
                foreach ($sensitivityBreakdown as $level => $count) {
                    if (($levelOrder[$level] ?? 0) > ($levelOrder[$maxLevel] ?? 0)) $maxLevel = $level;
                }
                $sensitivitySummary['max_level'] = $maxLevel;
            }
        } catch (\Exception $e) {}

        return view('research::research.compliance-dashboard', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'ethicsMilestones', 'ethicsStatus', 'odrlPolicies', 'odrlPolicyCount', 'sensitivityBreakdown', 'sensitivitySummary')
        ));
    }

    // =========================================================================
    // COLLABORATOR MANAGEMENT
    // =========================================================================

    public function inviteCollaborator(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post')) {
            $email = $request->input('email');
            $role = $request->input('role', 'contributor');

            $invitee = DB::table('research_researcher')->where('email', $email)->first();
            if (!$invitee) {
                return redirect()->route('research.inviteCollaborator', $id)->with('error', 'No researcher found with that email.');
            }

            $exists = DB::table('research_project_collaborator')
                ->where('project_id', $id)
                ->where('researcher_id', $invitee->id)
                ->first();
            if ($exists) {
                return redirect()->route('research.inviteCollaborator', $id)->with('error', 'Already a collaborator.');
            }

            DB::table('research_project_collaborator')->insert([
                'project_id'    => $id,
                'researcher_id' => $invitee->id,
                'role'          => $role,
                'status'        => 'invited',
                'invited_at'    => now(),
            ]);

            return redirect()->route('research.viewProject', $id)->with('success', 'Invitation sent to ' . $email);
        }

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        return view('research::research.invite-collaborator', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'collaborators')
        ));
    }

    public function shareProject(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post') && $request->input('form_action') === 'generate_token') {
            $token = bin2hex(random_bytes(32));
            DB::table('research_project')->where('id', $id)->update([
                'share_token' => $token,
                'updated_at'  => now(),
            ]);
            return redirect()->route('research.shareProject', $id)->with('success', 'Share link generated.');
        }

        return view('research::research.share-project', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    public function projectCollaborators(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        return view('research::research.project-collaborators', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'collaborators')
        ));
    }
}
