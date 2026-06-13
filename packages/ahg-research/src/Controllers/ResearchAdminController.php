<?php

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchService;
use AhgResearch\Contracts\UserProvisionerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResearchAdminController extends Controller
{
    protected ResearchService $service;
    protected UserProvisionerInterface $provisioner;

    public function __construct(ResearchService $service, UserProvisionerInterface $provisioner)
    {
        $this->service = $service;
        $this->provisioner = $provisioner;
    }

    public function researchers(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        // Admin middleware guards this route
        $filter = $request->input('filter', 'all');
        $query = $request->input('q');

        $statusFilter = ($filter !== 'all') ? $filter : null;
        $researchers = $this->service->getResearchers([
            'status' => $statusFilter,
            'search' => $query,
        ]);

        $counts = [
            'all' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->count(),
            'pending' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'pending')->count(),
            'approved' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'approved')->count(),
            'suspended' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'suspended')->count(),
            'expired' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'expired')->count(),
            'rejected' => (int) \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'rejected')->count(),
        ];

        return view('research::research.researchers', array_merge(
            method_exists($this, 'getSidebarData') ? $this->getSidebarData('researchers') : [],
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
                $this->provisioner->updateUser($researcher->user_id, ['active' => 1]);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Approved');
            } elseif ($action === 'suspend') {
                \Illuminate\Support\Facades\DB::table('research_researcher')->where('id', $id)->update(['status' => 'suspended']);
                return redirect()->route('research.viewResearcher', $id)->with('success', 'Suspended');
            }
        }

        // decrypt PII as ResearchController did
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
            method_exists($this, 'getSidebarData') ? $this->getSidebarData('researchers') : [],
            compact('researcher', 'bookings')
        ));
    }

    public function approveResearcher(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $this->service->approveResearcher($id, Auth::id());
        $this->provisioner->updateUser($researcher->user_id, ['active' => 1]);

        return redirect()->route('research.viewResearcher', $id)
            ->with('success', 'Researcher approved and account activated');
    }

    public function verifyResearcher(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcher($id);
        if (!$researcher) abort(404);

        $verified = (string) $request->input('verified', '1') === '1' ? 1 : 0;
        \Illuminate\Support\Facades\DB::table('research_researcher')->where('id', $id)->update([
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

        \Illuminate\Support\Facades\DB::table('research_researcher_audit')->insert([
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

        \Illuminate\Support\Facades\DB::table('research_researcher')->where('id', $id)->delete();
        $this->provisioner->deactivateUser($researcher->user_id);

        return redirect()->route('research.researchers')
            ->with('success', 'Researcher registration rejected and archived');
    }

    // You can add more admin methods (rooms, equipment, bookings etc.) as needed
}
