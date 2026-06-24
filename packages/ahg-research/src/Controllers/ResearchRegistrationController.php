<?php

/**
 * ResearchRegistrationController - Controller for Heratio
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
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Contracts\UserProvisionerInterface;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchRegistrationController - Researcher registration cluster.
 *
 * Extracted from ResearchController as a stage of the monolith decomposition
 * (issue #1269). This is the public-facing entry path into the research portal.
 *
 * Routes split across two middleware groups (see packages/ahg-research/routes/web.php):
 *   PUBLIC (no auth) - the no-auth `research.` group:
 *     - publicRegister()        GET  /research/publicRegister      research.publicRegister
 *     - publicRegister()        POST /research/public-register     research.publicRegister.store
 *     - registrationComplete()  GET  /research/registrationComplete research.registrationComplete
 *   AUTH (the auth `research.` group):
 *     - register()              GET/POST /research/register         research.register / research.register.store
 *     - renewal()               GET/POST /research/renewal          research.renewal
 *
 * storePublicRegistration() delegates to publicRegister() (kept as an in-class
 * private/public call - both moved together). All writes to the core user/ACL
 * tables route through the UserProvisioner contract (service call). No
 * cross-calls to other ResearchController methods existed - the methods used
 * only the shared trait helpers (getSidebarData) and the injected
 * ResearchService (registerResearcher + getResearcherByUserId), so the move is
 * a verbatim lift. The unused createAtomUser() compatibility shim (zero callers
 * package-wide, lived in the registration section) moved with the cluster.
 */
class ResearchRegistrationController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

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
                    $this->logResearchActivity('update', 'registration', (int) $existingResearcher->id, trim($request->input('first_name') . ' ' . $request->input('last_name')) ?: null, ['method' => 'ResearchRegistrationController@register']);
                    return redirect()->route('research.registrationComplete')
                        ->with('success', 'Re-registration submitted for review');
                } else {
                    $this->service->registerResearcher($data);
                    $this->logResearchActivity('create', 'registration', null, trim($request->input('first_name') . ' ' . $request->input('last_name')) ?: null, ['method' => 'ResearchRegistrationController@register']);
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

                $this->logResearchActivity('create', 'registration', null, trim($request->input('first_name') . ' ' . $request->input('last_name')) ?: ($email ?: null), ['method' => 'ResearchRegistrationController@publicRegister']);

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

    public function storePublicRegistration(Request $request)
    {
        return $this->publicRegister($request);
    }

    public function renewal(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        // Rejected researchers re-apply through this same route (the formerly
        // URL-unreachable register() carried the only re-apply path; its
        // /research/register URI is shadowed by the app LoginController route,
        // so the workspace "Re-apply" button now points here). Behaviour is
        // ported verbatim from register(): reset the existing
        // research_researcher row back to 'pending', clear the rejection
        // reason, refresh the profile fields, and confirm via the same flash.
        if ($researcher->status === 'rejected') {
            if ($request->isMethod('post')) {
                try {
                    // Reset the existing research_researcher row to pending and
                    // clear the rejection reason - the verbatim re-apply effect
                    // from register(). The profile fields are re-applied only
                    // when the request actually carries them (the full register
                    // form); the reason-only renewal form leaves the stored
                    // profile intact rather than nulling it. register() used a
                    // raw research_researcher update; the same mechanism is kept
                    // here (no core user-table writes).
                    $editable = [
                        'title', 'first_name', 'last_name', 'email', 'phone',
                        'affiliation_type', 'institution', 'department', 'position',
                        'research_interests', 'current_project', 'orcid_id',
                        'id_type', 'id_number', 'student_id',
                    ];
                    $data = ['status' => 'pending', 'rejection_reason' => null];
                    foreach ($editable as $field) {
                        if ($request->has($field)) {
                            $data[$field] = $request->input($field);
                        }
                    }
                    DB::table('research_researcher')
                        ->where('id', $researcher->id)
                        ->update($data);
                    $this->logResearchActivity('update', 'registration', (int) $researcher->id, trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null, ['method' => 'ResearchRegistrationController@renewal']);
                    return redirect()->route('research.registrationComplete')
                        ->with('success', 'Re-registration submitted for review');
                } catch (\Exception $e) {
                    return back()->with('error', $e->getMessage());
                }
            }

            return view('research::research.renewal', array_merge(
                $this->getSidebarData('profile'),
                compact('researcher')
            ));
        }

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
            $this->logResearchActivity('create', 'registration', (int) $researcher->id, trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null, ['method' => 'ResearchRegistrationController@renewal', 'request_type' => 'renewal']);
            return redirect()->route('research.profile')
                ->with('success', 'Renewal request submitted. You will be notified when reviewed.');
        }

        return view('research::research.renewal', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher')
        ));
    }
}
