<?php

namespace AhgSharePoint\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Phase 1 admin UI — index, tenants, drives, mapping.
 *
 * Mirror of atom-ahg-plugins/ahgSharePointPlugin/modules/sharepoint/actions/actions.class.php.
 */
class SharePointController extends Controller
{
    public function __construct()
    {
        // TODO: wire AHG admin auth middleware. Heratio admin gating goes here.
    }

    public function index()
    {
        // TODO: aggregate tenant/drive/sync_state counts for dashboard.
        return view('ahg-sharepoint::index');
    }

    public function tenants()
    {
        // TODO: list rows from SharePointTenantRepository::all().
        return view('ahg-sharepoint::tenants');
    }

    public function tenantEdit(Request $request, int $id)
    {
        // TODO: GET = render form; POST = validate + persist via repository.
        // client_secret field is write-only. Encrypt before persisting.
        return view('ahg-sharepoint::tenant-edit', ['id' => $id]);
    }

    public function tenantTest(Request $request, int $id)
    {
        // TODO: invoke same logic as `php artisan sharepoint:test-connection --tenant={id}`,
        // return JSON for AJAX consumer in tenant-edit page.
        return response()->json(['status' => 'not_implemented']);
    }

    public function drives()
    {
        return view('ahg-sharepoint::drives');
    }

    public function driveBrowse(Request $request)
    {
        // TODO: AJAX — given tenantId, GET /sites + drives via Graph; return JSON for picker.
        return response()->json(['status' => 'not_implemented']);
    }

    public function mapping(Request $request, int $id)
    {
        // TODO: GET = render mapping editor; POST = persist sharepoint_mapping rows.
        return view('ahg-sharepoint::mapping', ['driveId' => $id]);
    }

    // ---- Phase 2.A actions ----

    public function subscriptions(Request $request)
    {
        $rows = \Illuminate\Support\Facades\DB::table('sharepoint_subscription')
            ->orderBy('expires_at')
            ->get();
        return view('ahg-sharepoint::subscriptions', ['subscriptions' => $rows]);
    }

    public function events(Request $request)
    {
        $query = \Illuminate\Support\Facades\DB::table('sharepoint_event')
            ->orderByDesc('received_at')
            ->limit(200);
        $status = $request->query('status');
        if ($status) {
            $query->where('status', $status);
        }
        return view('ahg-sharepoint::events', [
            'events' => $query->get(),
            'statusFilter' => $status,
        ]);
    }

    public function eventDetail(Request $request, int $id)
    {
        $event = \Illuminate\Support\Facades\DB::table('sharepoint_event')->where('id', $id)->first();
        if ($event === null) {
            abort(404);
        }
        if ($request->isMethod('POST') && $request->input('form_action') === 'retry') {
            \AhgSharePoint\Jobs\IngestSharePointEventJob::dispatch($id)->onQueue('integrations');
            \Illuminate\Support\Facades\DB::table('sharepoint_event')
                ->where('id', $id)
                ->update(['status' => 'queued', 'last_error' => null]);
            return redirect()->route('sharepoint.events.detail', ['id' => $id]);
        }
        return view('ahg-sharepoint::event-detail', ['event' => $event]);
    }
}
