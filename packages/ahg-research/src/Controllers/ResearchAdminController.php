<?php

/**
 * ResearchAdminController - Controller for Heratio
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
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchAdminController - Researcher administration (ACL cluster).
 *
 * Extracted from ResearchController as the researchers-admin stage of the
 * monolith decomposition (issue #1269). These seven endpoints are the core
 * auth/ACL writers of the research portal: they list/approve/verify/reject/
 * suspend researchers and reset their passwords, mutating the linked `user`
 * account through the canonical UserProvisioner contract (never a direct
 * write to the auth tables). All seven are admin-gated in routes/web.php.
 *
 * No cross-calls to other ResearchController methods existed - the bodies use
 * only the shared trait helper (getSidebarData), the injected ResearchService,
 * the UserProvisionerInterface resolved via the container, and the DB facade,
 * so the move is a verbatim lift.
 */
class ResearchAdminController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

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

    // ---------------------------------------------------------------------
    // #1390 #4a - moderation review queue for metadata suggestions synced
    // back from offline / portable packages (OfflineSyncService queues them
    // into research_metadata_suggestion with status='open').
    // ---------------------------------------------------------------------

    /** User-entered field label -> information_object_i18n column, for auto-apply on approve. */
    private function suggestionFieldMap(): array
    {
        return [
            'title' => 'title', 'alternatetitle' => 'alternate_title', 'alternativetitle' => 'alternate_title',
            'scopeandcontent' => 'scope_and_content', 'scope' => 'scope_and_content', 'description' => 'scope_and_content',
            'arrangement' => 'arrangement', 'archivalhistory' => 'archival_history', 'custodialhistory' => 'archival_history',
            'extentandmedium' => 'extent_and_medium', 'extent' => 'extent_and_medium',
            'accessconditions' => 'access_conditions', 'conditionsgoverningaccess' => 'access_conditions',
            'physicalcharacteristics' => 'physical_characteristics', 'findingaids' => 'finding_aids',
            'acquisition' => 'acquisition', 'appraisal' => 'appraisal', 'accruals' => 'accruals',
        ];
    }

    /** Curator review queue for offline-synced metadata suggestions. */
    public function metadataSuggestions(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $filter = (string) $request->query('filter', 'open');

        $counts = [
            'open'     => DB::table('research_metadata_suggestion')->where('status', 'open')->count(),
            'approved' => DB::table('research_metadata_suggestion')->where('status', 'approved')->count(),
            'rejected' => DB::table('research_metadata_suggestion')->where('status', 'rejected')->count(),
        ];

        $q = DB::table('research_metadata_suggestion as s')
            ->leftJoin('research_researcher as rr', 'rr.id', '=', 's.researcher_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 's.object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 's.object_id')
            ->select('s.*', 'ioi.title as record_title', 'slug.slug as record_slug',
                'rr.first_name', 'rr.last_name');
        if (in_array($filter, ['open', 'approved', 'rejected'], true)) {
            $q->where('s.status', $filter);
        }
        $suggestions = $q->orderByDesc('s.created_at')->limit(500)->get();

        return view('research::research.metadata-suggestions', array_merge(
            $this->getSidebarData('metadataSuggestions'),
            compact('suggestions', 'filter', 'counts')
        ));
    }

    /** Approve: apply to the record's field when auto-mappable, then mark approved. */
    public function approveMetadataSuggestion(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $s = DB::table('research_metadata_suggestion')->where('id', $id)->first();
        if (! $s || $s->status !== 'open') {
            return redirect()->route('research.admin.metadataSuggestions')
                ->with('error', 'Suggestion not found or already reviewed.');
        }

        $key = preg_replace('/[^a-z]/', '', strtolower((string) $s->field));
        $col = $this->suggestionFieldMap()[$key] ?? null;
        $applied = false;
        if ($col && (int) $s->object_id > 0) {
            DB::table('information_object_i18n')->updateOrInsert(
                ['id' => (int) $s->object_id, 'culture' => 'en'],
                [$col => (string) $s->suggestion]
            );
            $applied = true;
        }

        DB::table('research_metadata_suggestion')->where('id', $id)->update([
            'status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now(),
        ]);

        return redirect()->route('research.admin.metadataSuggestions')->with('success',
            $applied
                ? "Approved and applied to the record's \"{$s->field}\" field."
                : "Approved. The field \"{$s->field}\" has no auto-mapping - apply it to the record manually.");
    }

    /** Reject a suggestion (no change to the record). */
    public function rejectMetadataSuggestion(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $updated = DB::table('research_metadata_suggestion')->where('id', $id)->where('status', 'open')->update([
            'status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now(),
        ]);

        return redirect()->route('research.admin.metadataSuggestions')->with(
            $updated ? 'success' : 'error',
            $updated ? 'Suggestion rejected.' : 'Suggestion not found or already reviewed.');
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
                $this->logResearchActivity(
                    'approve',
                    'research_admin',
                    $id,
                    trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
                    ['method' => 'ResearchAdminController@viewResearcher', 'action' => 'approve']
                );
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Approved');
            } elseif ($action === 'suspend') {
                DB::table('research_researcher')->where('id', $id)->update(['status' => 'suspended']);
                // Also deactivate the linked account, consistent with suspendResearcher().
                $provisioner->deactivateUser($researcher->user_id);
                $this->logResearchActivity(
                    'update',
                    'research_admin',
                    $id,
                    trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
                    ['method' => 'ResearchAdminController@viewResearcher', 'action' => 'suspend']
                );
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

        $this->logResearchActivity(
            'approve',
            'research_admin',
            $id,
            trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
            ['method' => 'ResearchAdminController@approveResearcher']
        );

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

        $this->logResearchActivity(
            'update',
            'research_admin',
            $id,
            trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
            ['method' => 'ResearchAdminController@verifyResearcher', 'verified' => $verified]
        );

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

        $this->logResearchActivity(
            'reject',
            'research_admin',
            $id,
            trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
            ['method' => 'ResearchAdminController@rejectResearcher']
        );

        return redirect()->route('research.researchers')
            ->with('success', 'Researcher registration rejected and archived');
    }

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

        $this->logResearchActivity(
            'update',
            'research_admin',
            $id,
            trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
            ['method' => 'ResearchAdminController@resetPassword']
        );

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

        $this->logResearchActivity(
            'update',
            'research_admin',
            $id,
            trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: null,
            ['method' => 'ResearchAdminController@suspendResearcher', 'action' => 'suspend']
        );

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher suspended');
    }
}
