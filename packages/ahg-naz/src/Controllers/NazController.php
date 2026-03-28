<?php

namespace AhgNaz\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NazController extends Controller
{
    // ─── helpers ──────────────────────────────────────────────────────

    /**
     * Write an entry to naz_audit_log.
     */
    private function audit(string $action, string $entityType, int $entityId, ?array $old = null, ?array $new = null, ?string $notes = null): void
    {
        DB::table('naz_audit_log')->insert([
            'action_type'  => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'user_id'      => Auth::id(),
            'ip_address'   => request()->ip(),
            'old_value'    => $old ? json_encode($old) : null,
            'new_value'    => $new ? json_encode($new) : null,
            'notes'        => $notes,
            'created_at'   => now(),
        ]);
    }

    /**
     * Get a single config value.
     */
    private function cfg(string $key, ?string $default = null): ?string
    {
        $row = DB::table('naz_config')->where('config_key', $key)->first();

        return $row ? $row->config_value : $default;
    }

    // ─── Dashboard ───────────────────────────────────────────────────

    public function index()
    {
        $closuresActive    = DB::table('naz_closure_period')->where('status', 'active')->count();
        $closuresExpiring  = DB::table('naz_closure_period')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays(90))
            ->count();

        $protectedActive   = DB::table('naz_protected_record')->where('status', 'active')->count();
        $schedulesActive   = DB::table('naz_records_schedule')->where('status', 'active')->count();
        $schedulesDraft    = DB::table('naz_records_schedule')->where('status', 'draft')->count();

        $permitsPending    = DB::table('naz_research_permit')->where('status', 'pending')->count();
        $permitsActive     = DB::table('naz_research_permit')->where('status', 'approved')->count();
        $permitsExpiring   = DB::table('naz_research_permit')
            ->where('status', 'approved')
            ->where('end_date', '<=', now()->addDays(30))
            ->count();

        $researchersActive = DB::table('naz_researcher')->where('status', 'active')->count();

        $transfersProposed = DB::table('naz_transfer')->where('status', 'proposed')->count();
        $transfersInTransit = DB::table('naz_transfer')->where('status', 'in_transit')->count();
        $transfersReceived = DB::table('naz_transfer')->where('status', 'received')->count();

        $visitsToday       = DB::table('naz_research_visit')->where('visit_date', today())->count();

        $recentAudit = DB::table('naz_audit_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('naz::index', compact(
            'closuresActive', 'closuresExpiring',
            'protectedActive',
            'schedulesActive', 'schedulesDraft',
            'permitsPending', 'permitsActive', 'permitsExpiring',
            'researchersActive',
            'transfersProposed', 'transfersInTransit', 'transfersReceived',
            'visitsToday', 'recentAudit',
        ));
    }

    // ─── Config (Settings) ──────────────────────────────────────────

    public function config()
    {
        $settings = DB::table('naz_config')->orderBy('config_key')->get();

        return view('naz::config', compact('settings'));
    }

    public function configStore(Request $request)
    {
        $request->validate([
            'settings'               => 'required|array',
            'settings.*.config_key'  => 'required|string|max:100',
            'settings.*.config_value' => 'nullable|string',
        ]);

        foreach ($request->input('settings', []) as $item) {
            $key = $item['config_key'];
            $val = $item['config_value'] ?? null;

            $existing = DB::table('naz_config')->where('config_key', $key)->first();
            $oldVal   = $existing ? $existing->config_value : null;

            DB::table('naz_config')->updateOrInsert(
                ['config_key' => $key],
                [
                    'config_value' => $val,
                    'updated_at'   => now(),
                ]
            );

            if ($oldVal !== $val) {
                $this->audit('update', 'config', $existing->id ?? 0, ['value' => $oldVal], ['value' => $val], "Config key: {$key}");
            }
        }

        return redirect()->route('ahgnaz.config')->with('success', 'Settings saved.');
    }

    // ─── Closure Periods ────────────────────────────────────────────

    public function closures(Request $request)
    {
        $query = DB::table('naz_closure_period as cp')
            ->leftJoin('information_object as io', 'cp.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->select('cp.*', 'ioi.title as io_title');

        if ($status = $request->get('status')) {
            $query->where('cp.status', $status);
        }
        if ($type = $request->get('closure_type')) {
            $query->where('cp.closure_type', $type);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('ioi.title', 'like', "%{$search}%")
                  ->orWhere('cp.closure_reason', 'like', "%{$search}%")
                  ->orWhere('cp.authority_reference', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'start_date');
        $dir  = $request->get('sortDir', 'desc');
        $allowed = ['start_date', 'end_date', 'closure_type', 'status', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy("cp.{$sort}", $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('cp.start_date');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::closures', compact('rows', 'total', 'page', 'limit'));
    }

    public function closureCreate()
    {
        return view('naz::closure-create');
    }

    public function closureStore(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => 'required|integer|exists:information_object,id',
            'closure_type'          => 'required|string|max:50',
            'closure_reason'        => 'nullable|string|max:255',
            'start_date'            => 'required|date',
            'end_date'              => 'nullable|date|after_or_equal:start_date',
            'years'                 => 'nullable|integer|min:0',
            'authority_reference'   => 'nullable|string|max:100',
            'review_date'           => 'nullable|date',
            'status'                => 'nullable|string|max:42',
            'release_notes'         => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('naz_closure_period')->insertGetId($data);

        $this->audit('create', 'closure_period', $id, null, $data);

        return redirect()->route('ahgnaz.closures')->with('success', 'Closure period created.');
    }

    public function closureEdit(int $id)
    {
        $closure = DB::table('naz_closure_period')->where('id', $id)->firstOrFail();

        $ioTitle = DB::table('information_object_i18n')
            ->where('id', $closure->information_object_id)
            ->where('culture', app()->getLocale())
            ->value('title');

        return view('naz::closure-edit', compact('closure', 'ioTitle'));
    }

    public function closureUpdate(Request $request, int $id)
    {
        $old = DB::table('naz_closure_period')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'closure_type'        => 'required|string|max:50',
            'closure_reason'      => 'nullable|string|max:255',
            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'years'               => 'nullable|integer|min:0',
            'authority_reference' => 'nullable|string|max:100',
            'review_date'         => 'nullable|date',
            'status'              => 'nullable|string|max:42',
            'release_notes'       => 'nullable|string',
            'released_by'         => 'nullable|integer',
            'released_at'         => 'nullable|date',
        ]);

        $data['updated_at'] = now();

        DB::table('naz_closure_period')->where('id', $id)->update($data);

        $this->audit('update', 'closure_period', $id, (array) $old, $data);

        return redirect()->route('ahgnaz.closure-edit', $id)->with('success', 'Closure period updated.');
    }

    // ─── Protected Records ──────────────────────────────────────────

    public function protectedRecords(Request $request)
    {
        $query = DB::table('naz_protected_record as pr')
            ->leftJoin('information_object as io', 'pr.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->select('pr.*', 'ioi.title as io_title');

        if ($status = $request->get('status')) {
            $query->where('pr.status', $status);
        }
        if ($type = $request->get('protection_type')) {
            $query->where('pr.protection_type', $type);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('ioi.title', 'like', "%{$search}%")
                  ->orWhere('pr.protection_reason', 'like', "%{$search}%")
                  ->orWhere('pr.authority_reference', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'protection_start');
        $dir  = $request->get('sortDir', 'desc');
        $allowed = ['protection_start', 'protection_end', 'protection_type', 'status', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy("pr.{$sort}", $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('pr.protection_start');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::protected-records', compact('rows', 'total', 'page', 'limit'));
    }

    // ─── Retention Schedules ────────────────────────────────────────

    public function schedules(Request $request)
    {
        $query = DB::table('naz_records_schedule');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($action = $request->get('disposal_action')) {
            $query->where('disposal_action', $action);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', "%{$search}%")
                  ->orWhere('agency_name', 'like', "%{$search}%")
                  ->orWhere('record_series', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'schedule_number');
        $dir  = $request->get('sortDir', 'asc');
        $allowed = ['schedule_number', 'agency_name', 'record_series', 'disposal_action', 'status', 'effective_date', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $dir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('schedule_number');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::schedules', compact('rows', 'total', 'page', 'limit'));
    }

    public function scheduleCreate()
    {
        return view('naz::schedule-create');
    }

    public function scheduleStore(Request $request)
    {
        $data = $request->validate([
            'schedule_number'          => 'required|string|max:50|unique:naz_records_schedule,schedule_number',
            'agency_name'              => 'required|string|max:255',
            'agency_code'              => 'nullable|string|max:50',
            'record_series'            => 'required|string|max:255',
            'description'              => 'nullable|string',
            'retention_period_active'  => 'required|integer|min:0',
            'retention_period_semi'    => 'nullable|integer|min:0',
            'disposal_action'          => 'required|string|max:43',
            'legal_authority'          => 'nullable|string',
            'classification'           => 'nullable|string|max:46',
            'access_restriction'       => 'nullable|string|max:45',
            'approved_by'              => 'nullable|string|max:255',
            'approval_date'            => 'nullable|date',
            'effective_date'           => 'nullable|date',
            'review_date'              => 'nullable|date',
            'status'                   => 'nullable|string|max:52',
            'notes'                    => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('naz_records_schedule')->insertGetId($data);

        $this->audit('create', 'records_schedule', $id, null, $data);

        return redirect()->route('ahgnaz.schedule-view', $id)->with('success', 'Retention schedule created.');
    }

    public function scheduleView(int $id)
    {
        $schedule = DB::table('naz_records_schedule')->where('id', $id)->firstOrFail();

        // Transfers linked to this schedule
        $transfers = DB::table('naz_transfer')
            ->where('schedule_id', $id)
            ->orderByDesc('proposed_date')
            ->get();

        return view('naz::schedule-view', compact('schedule', 'transfers'));
    }

    public function scheduleUpdate(Request $request, int $id)
    {
        $old = DB::table('naz_records_schedule')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'agency_name'              => 'required|string|max:255',
            'agency_code'              => 'nullable|string|max:50',
            'record_series'            => 'required|string|max:255',
            'description'              => 'nullable|string',
            'retention_period_active'  => 'required|integer|min:0',
            'retention_period_semi'    => 'nullable|integer|min:0',
            'disposal_action'          => 'required|string|max:43',
            'legal_authority'          => 'nullable|string',
            'classification'           => 'nullable|string|max:46',
            'access_restriction'       => 'nullable|string|max:45',
            'approved_by'              => 'nullable|string|max:255',
            'approval_date'            => 'nullable|date',
            'effective_date'           => 'nullable|date',
            'review_date'              => 'nullable|date',
            'status'                   => 'nullable|string|max:52',
            'notes'                    => 'nullable|string',
        ]);

        $data['updated_at'] = now();

        DB::table('naz_records_schedule')->where('id', $id)->update($data);

        $this->audit('update', 'records_schedule', $id, (array) $old, $data);

        return redirect()->route('ahgnaz.schedule-view', $id)->with('success', 'Retention schedule updated.');
    }

    // ─── Research Permits ───────────────────────────────────────────

    public function permits(Request $request)
    {
        $query = DB::table('naz_research_permit as p')
            ->leftJoin('naz_researcher as r', 'p.researcher_id', '=', 'r.id')
            ->select('p.*', 'r.first_name as researcher_first', 'r.last_name as researcher_last', 'r.email as researcher_email');

        if ($status = $request->get('status')) {
            $query->where('p.status', $status);
        }
        if ($type = $request->get('permit_type')) {
            $query->where('p.permit_type', $type);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('p.permit_number', 'like', "%{$search}%")
                  ->orWhere('p.research_topic', 'like', "%{$search}%")
                  ->orWhere('r.first_name', 'like', "%{$search}%")
                  ->orWhere('r.last_name', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'start_date');
        $dir  = $request->get('sortDir', 'desc');
        $allowed = ['permit_number', 'start_date', 'end_date', 'permit_type', 'status', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy("p.{$sort}", $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('p.start_date');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::permits', compact('rows', 'total', 'page', 'limit'));
    }

    public function permitCreate()
    {
        $researchers = DB::table('naz_researcher')
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('naz::permit-create', compact('researchers'));
    }

    public function permitStore(Request $request)
    {
        $data = $request->validate([
            'permit_number'      => 'required|string|max:50|unique:naz_research_permit,permit_number',
            'researcher_id'      => 'required|integer|exists:naz_researcher,id',
            'permit_type'        => 'required|string|max:36',
            'research_topic'     => 'required|string|max:500',
            'research_purpose'   => 'nullable|string',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'fee_amount'         => 'nullable|numeric|min:0',
            'fee_currency'       => 'nullable|string|max:3',
            'fee_paid'           => 'nullable|boolean',
            'fee_receipt'        => 'nullable|string|max:100',
            'payment_date'       => 'nullable|date',
            'status'             => 'nullable|string|max:58',
            'collections_access' => 'nullable|string',
            'restrictions'       => 'nullable|string',
        ]);

        // collections_access stored as JSON
        if (isset($data['collections_access']) && is_string($data['collections_access'])) {
            $decoded = json_decode($data['collections_access'], true);
            $data['collections_access'] = $decoded !== null ? json_encode($decoded) : json_encode([]);
        }

        $data['fee_paid']   = $request->boolean('fee_paid') ? 1 : 0;
        $data['created_by'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('naz_research_permit')->insertGetId($data);

        $this->audit('create', 'research_permit', $id, null, $data);

        return redirect()->route('ahgnaz.permit-view', $id)->with('success', 'Research permit created.');
    }

    public function permitView(int $id)
    {
        $permit = DB::table('naz_research_permit')->where('id', $id)->firstOrFail();

        $researcher = DB::table('naz_researcher')->where('id', $permit->researcher_id)->first();

        $visits = DB::table('naz_research_visit')
            ->where('permit_id', $id)
            ->orderByDesc('visit_date')
            ->get();

        return view('naz::permit-view', compact('permit', 'researcher', 'visits'));
    }

    public function permitUpdate(Request $request, int $id)
    {
        $old = DB::table('naz_research_permit')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'permit_type'        => 'required|string|max:36',
            'research_topic'     => 'required|string|max:500',
            'research_purpose'   => 'nullable|string',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'fee_amount'         => 'nullable|numeric|min:0',
            'fee_currency'       => 'nullable|string|max:3',
            'fee_paid'           => 'nullable|boolean',
            'fee_receipt'        => 'nullable|string|max:100',
            'payment_date'       => 'nullable|date',
            'approved_by'        => 'nullable|integer',
            'approved_date'      => 'nullable|date',
            'status'             => 'nullable|string|max:58',
            'rejection_reason'   => 'nullable|string',
            'collections_access' => 'nullable|string',
            'restrictions'       => 'nullable|string',
        ]);

        if (isset($data['collections_access']) && is_string($data['collections_access'])) {
            $decoded = json_decode($data['collections_access'], true);
            $data['collections_access'] = $decoded !== null ? json_encode($decoded) : json_encode([]);
        }

        $data['fee_paid']   = $request->boolean('fee_paid') ? 1 : 0;
        $data['updated_at'] = now();

        DB::table('naz_research_permit')->where('id', $id)->update($data);

        $this->audit('update', 'research_permit', $id, (array) $old, $data);

        return redirect()->route('ahgnaz.permit-view', $id)->with('success', 'Research permit updated.');
    }

    // ─── Researchers ────────────────────────────────────────────────

    public function researchers(Request $request)
    {
        $query = DB::table('naz_researcher');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->get('researcher_type')) {
            $query->where('researcher_type', $type);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('institution', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'last_name');
        $dir  = $request->get('sortDir', 'asc');
        $allowed = ['last_name', 'first_name', 'email', 'institution', 'researcher_type', 'status', 'registration_date', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $dir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('last_name');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::researchers', compact('rows', 'total', 'page', 'limit'));
    }

    public function researcherCreate()
    {
        return view('naz::researcher-create');
    }

    public function researcherStore(Request $request)
    {
        $data = $request->validate([
            'user_id'            => 'nullable|integer',
            'researcher_type'    => 'required|string|max:37',
            'title'              => 'nullable|string|max:20',
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:50',
            'nationality'        => 'nullable|string|max:100',
            'passport_number'    => 'nullable|string|max:50',
            'national_id'        => 'nullable|string|max:50',
            'institution'        => 'nullable|string|max:255',
            'position'           => 'nullable|string|max:100',
            'address'            => 'nullable|string',
            'city'               => 'nullable|string|max:100',
            'country'            => 'nullable|string|max:100',
            'research_interests' => 'nullable|string',
            'registration_date'  => 'required|date',
            'status'             => 'nullable|string|max:47',
            'notes'              => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('naz_researcher')->insertGetId($data);

        $this->audit('create', 'researcher', $id, null, $data);

        return redirect()->route('ahgnaz.researcher-view', $id)->with('success', 'Researcher registered.');
    }

    public function researcherView(int $id)
    {
        $researcher = DB::table('naz_researcher')->where('id', $id)->firstOrFail();

        // Permits for this researcher
        $permits = DB::table('naz_research_permit')
            ->where('researcher_id', $id)
            ->orderByDesc('start_date')
            ->get();

        // Recent visits
        $visits = DB::table('naz_research_visit')
            ->where('researcher_id', $id)
            ->orderByDesc('visit_date')
            ->limit(50)
            ->get();

        return view('naz::researcher-view', compact('researcher', 'permits', 'visits'));
    }

    public function researcherUpdate(Request $request, int $id)
    {
        $old = DB::table('naz_researcher')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'user_id'            => 'nullable|integer',
            'researcher_type'    => 'required|string|max:37',
            'title'              => 'nullable|string|max:20',
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:50',
            'nationality'        => 'nullable|string|max:100',
            'passport_number'    => 'nullable|string|max:50',
            'national_id'        => 'nullable|string|max:50',
            'institution'        => 'nullable|string|max:255',
            'position'           => 'nullable|string|max:100',
            'address'            => 'nullable|string',
            'city'               => 'nullable|string|max:100',
            'country'            => 'nullable|string|max:100',
            'research_interests' => 'nullable|string',
            'registration_date'  => 'required|date',
            'status'             => 'nullable|string|max:47',
            'notes'              => 'nullable|string',
        ]);

        $data['updated_at'] = now();

        DB::table('naz_researcher')->where('id', $id)->update($data);

        $this->audit('update', 'researcher', $id, (array) $old, $data);

        return redirect()->route('ahgnaz.researcher-view', $id)->with('success', 'Researcher updated.');
    }

    // ─── Transfers ──────────────────────────────────────────────────

    public function transfers(Request $request)
    {
        $query = DB::table('naz_transfer as t')
            ->leftJoin('naz_records_schedule as s', 't.schedule_id', '=', 's.id')
            ->select('t.*', 's.schedule_number', 's.agency_name as schedule_agency');

        if ($status = $request->get('status')) {
            $query->where('t.status', $status);
        }
        if ($type = $request->get('transfer_type')) {
            $query->where('t.transfer_type', $type);
        }
        if ($search = $request->get('query')) {
            $query->where(function ($q) use ($search) {
                $q->where('t.transfer_number', 'like', "%{$search}%")
                  ->orWhere('t.transferring_agency', 'like', "%{$search}%")
                  ->orWhere('t.description', 'like', "%{$search}%")
                  ->orWhere('t.accession_number', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'proposed_date');
        $dir  = $request->get('sortDir', 'desc');
        $allowed = ['transfer_number', 'transferring_agency', 'transfer_type', 'proposed_date', 'actual_date', 'status', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy("t.{$sort}", $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('t.proposed_date');
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 25)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::transfers', compact('rows', 'total', 'page', 'limit'));
    }

    public function transferCreate()
    {
        $schedules = DB::table('naz_records_schedule')
            ->whereIn('status', ['active', 'approved'])
            ->orderBy('schedule_number')
            ->get(['id', 'schedule_number', 'agency_name', 'record_series']);

        return view('naz::transfer-create', compact('schedules'));
    }

    public function transferStore(Request $request)
    {
        $data = $request->validate([
            'transfer_number'         => 'required|string|max:50|unique:naz_transfer,transfer_number',
            'transferring_agency'     => 'required|string|max:255',
            'agency_contact'          => 'nullable|string|max:255',
            'agency_email'            => 'nullable|email|max:255',
            'agency_phone'            => 'nullable|string|max:50',
            'schedule_id'             => 'nullable|integer|exists:naz_records_schedule,id',
            'transfer_type'           => 'nullable|string|max:45',
            'description'             => 'nullable|string',
            'date_range_start'        => 'nullable|date',
            'date_range_end'          => 'nullable|date|after_or_equal:date_range_start',
            'quantity_linear_metres'  => 'nullable|numeric|min:0',
            'quantity_boxes'          => 'nullable|integer|min:0',
            'quantity_items'          => 'nullable|integer|min:0',
            'contains_restricted'     => 'nullable|boolean',
            'restriction_details'     => 'nullable|string',
            'accession_number'        => 'nullable|string|max:100',
            'proposed_date'           => 'nullable|date',
            'actual_date'             => 'nullable|date',
            'received_by'             => 'nullable|integer',
            'status'                  => 'nullable|string|max:79',
            'location_assigned'       => 'nullable|string|max:255',
            'notes'                   => 'nullable|string',
        ]);

        $data['contains_restricted'] = $request->boolean('contains_restricted') ? 1 : 0;
        $data['created_by'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('naz_transfer')->insertGetId($data);

        // Insert transfer items if provided
        $items = $request->input('items', []);
        foreach ($items as $item) {
            if (empty($item['series_title'])) {
                continue;
            }
            DB::table('naz_transfer_item')->insert([
                'transfer_id'        => $id,
                'series_title'       => $item['series_title'],
                'description'        => $item['description'] ?? null,
                'date_range'         => $item['date_range'] ?? null,
                'quantity'           => $item['quantity'] ?? 1,
                'format'             => $item['format'] ?? null,
                'condition_notes'    => $item['condition_notes'] ?? null,
                'access_restriction' => $item['access_restriction'] ?? 'open',
                'restriction_end_date' => $item['restriction_end_date'] ?? null,
                'information_object_id' => $item['information_object_id'] ?? null,
                'created_at'         => now(),
            ]);
        }

        $this->audit('create', 'transfer', $id, null, $data);

        return redirect()->route('ahgnaz.transfer-view', $id)->with('success', 'Transfer created.');
    }

    public function transferView(int $id)
    {
        $transfer = DB::table('naz_transfer')->where('id', $id)->firstOrFail();

        $schedule = $transfer->schedule_id
            ? DB::table('naz_records_schedule')->where('id', $transfer->schedule_id)->first()
            : null;

        $items = DB::table('naz_transfer_item')
            ->where('transfer_id', $id)
            ->orderBy('id')
            ->get();

        return view('naz::transfer-view', compact('transfer', 'schedule', 'items'));
    }

    public function transferUpdate(Request $request, int $id)
    {
        $old = DB::table('naz_transfer')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'transferring_agency'     => 'required|string|max:255',
            'agency_contact'          => 'nullable|string|max:255',
            'agency_email'            => 'nullable|email|max:255',
            'agency_phone'            => 'nullable|string|max:50',
            'schedule_id'             => 'nullable|integer|exists:naz_records_schedule,id',
            'transfer_type'           => 'nullable|string|max:45',
            'description'             => 'nullable|string',
            'date_range_start'        => 'nullable|date',
            'date_range_end'          => 'nullable|date|after_or_equal:date_range_start',
            'quantity_linear_metres'  => 'nullable|numeric|min:0',
            'quantity_boxes'          => 'nullable|integer|min:0',
            'quantity_items'          => 'nullable|integer|min:0',
            'contains_restricted'     => 'nullable|boolean',
            'restriction_details'     => 'nullable|string',
            'accession_number'        => 'nullable|string|max:100',
            'proposed_date'           => 'nullable|date',
            'actual_date'             => 'nullable|date',
            'received_by'             => 'nullable|integer',
            'status'                  => 'nullable|string|max:79',
            'rejection_reason'        => 'nullable|string',
            'location_assigned'       => 'nullable|string|max:255',
            'notes'                   => 'nullable|string',
        ]);

        $data['contains_restricted'] = $request->boolean('contains_restricted') ? 1 : 0;
        $data['updated_at'] = now();

        DB::table('naz_transfer')->where('id', $id)->update($data);

        $this->audit('update', 'transfer', $id, (array) $old, $data);

        return redirect()->route('ahgnaz.transfer-view', $id)->with('success', 'Transfer updated.');
    }

    // ─── Reports ────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $reportType = $request->get('type', 'summary');
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $reportData = [];

        switch ($reportType) {
            case 'closures':
                $q = DB::table('naz_closure_period')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status');
                if ($dateFrom) $q->where('start_date', '>=', $dateFrom);
                if ($dateTo) $q->where('start_date', '<=', $dateTo);
                $reportData['closures_by_status'] = $q->get();

                $reportData['closures_expiring'] = DB::table('naz_closure_period')
                    ->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->where('end_date', '<=', now()->addMonths(6))
                    ->orderBy('end_date')
                    ->get();
                break;

            case 'protected':
                $q = DB::table('naz_protected_record')
                    ->select('protection_type', 'status', DB::raw('COUNT(*) as total'))
                    ->groupBy('protection_type', 'status');
                $reportData['protected_by_type'] = $q->get();

                $reportData['protected_reviews_due'] = DB::table('naz_protected_record')
                    ->where('status', 'active')
                    ->whereNotNull('review_date')
                    ->where('review_date', '<=', now()->addMonths(3))
                    ->orderBy('review_date')
                    ->get();
                break;

            case 'schedules':
                $reportData['schedules_by_status'] = DB::table('naz_records_schedule')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->get();

                $reportData['schedules_by_action'] = DB::table('naz_records_schedule')
                    ->select('disposal_action', DB::raw('COUNT(*) as total'))
                    ->groupBy('disposal_action')
                    ->get();

                $reportData['schedules_reviews_due'] = DB::table('naz_records_schedule')
                    ->where('status', 'active')
                    ->whereNotNull('review_date')
                    ->where('review_date', '<=', now()->addMonths(3))
                    ->orderBy('review_date')
                    ->get();
                break;

            case 'permits':
                $reportData['permits_by_status'] = DB::table('naz_research_permit')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->get();

                $reportData['permits_by_type'] = DB::table('naz_research_permit')
                    ->select('permit_type', DB::raw('COUNT(*) as total'))
                    ->groupBy('permit_type')
                    ->get();

                $q = DB::table('naz_research_visit')
                    ->select(DB::raw('DATE_FORMAT(visit_date, "%Y-%m") as month'), DB::raw('COUNT(*) as total'));
                if ($dateFrom) $q->where('visit_date', '>=', $dateFrom);
                if ($dateTo) $q->where('visit_date', '<=', $dateTo);
                $reportData['visits_by_month'] = $q->groupBy('month')->orderBy('month')->get();
                break;

            case 'transfers':
                $reportData['transfers_by_status'] = DB::table('naz_transfer')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->get();

                $reportData['transfers_by_type'] = DB::table('naz_transfer')
                    ->select('transfer_type', DB::raw('COUNT(*) as total'))
                    ->groupBy('transfer_type')
                    ->get();

                $reportData['transfers_volume'] = DB::table('naz_transfer')
                    ->select(
                        DB::raw('SUM(quantity_linear_metres) as total_metres'),
                        DB::raw('SUM(quantity_boxes) as total_boxes'),
                        DB::raw('SUM(quantity_items) as total_items'),
                        DB::raw('COUNT(*) as total_transfers')
                    )
                    ->first();
                break;

            case 'audit':
                $q = DB::table('naz_audit_log')
                    ->select('action_type', 'entity_type', DB::raw('COUNT(*) as total'))
                    ->groupBy('action_type', 'entity_type');
                if ($dateFrom) $q->where('created_at', '>=', $dateFrom);
                if ($dateTo) $q->where('created_at', '<=', $dateTo . ' 23:59:59');
                $reportData['audit_summary'] = $q->get();

                $q2 = DB::table('naz_audit_log')
                    ->select(DB::raw('DATE(created_at) as log_date'), DB::raw('COUNT(*) as total'))
                    ->groupBy('log_date')
                    ->orderByDesc('log_date')
                    ->limit(30);
                if ($dateFrom) $q2->where('created_at', '>=', $dateFrom);
                if ($dateTo) $q2->where('created_at', '<=', $dateTo . ' 23:59:59');
                $reportData['audit_by_day'] = $q2->get();
                break;

            default: // summary
                $reportData['totals'] = [
                    'closures_active'    => DB::table('naz_closure_period')->where('status', 'active')->count(),
                    'protected_active'   => DB::table('naz_protected_record')->where('status', 'active')->count(),
                    'schedules_active'   => DB::table('naz_records_schedule')->where('status', 'active')->count(),
                    'permits_active'     => DB::table('naz_research_permit')->where('status', 'approved')->count(),
                    'researchers_active' => DB::table('naz_researcher')->where('status', 'active')->count(),
                    'transfers_total'    => DB::table('naz_transfer')->count(),
                    'visits_total'       => DB::table('naz_research_visit')->count(),
                    'audit_entries'      => DB::table('naz_audit_log')->count(),
                ];
                break;
        }

        return view('naz::reports', compact('reportType', 'dateFrom', 'dateTo', 'reportData'));
    }

    // ─── Audit Log ──────────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $query = DB::table('naz_audit_log');

        if ($actionType = $request->get('action_type')) {
            $query->where('action_type', $actionType);
        }
        if ($entityType = $request->get('entity_type')) {
            $query->where('entity_type', $entityType);
        }
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $query->orderByDesc('created_at');

        $page  = max(1, (int) $request->get('page', 1));
        $limit = max(1, min(100, (int) $request->get('limit', 50)));
        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return view('naz::audit-log', compact('rows', 'total', 'page', 'limit'));
    }
}
