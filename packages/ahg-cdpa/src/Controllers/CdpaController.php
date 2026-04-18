<?php

/**
 * CdpaController - Controller for Heratio
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



namespace AhgCdpa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CdpaController extends Controller
{
    // ─── helpers ──────────────────────────────────────────────────────

    /**
     * Write an entry to cdpa_audit_log.
     */
    protected function audit(string $action, string $entityType, ?int $entityId = null, ?array $details = null): void
    {
        if (Schema::hasTable('cdpa_audit_log')) {
            DB::table('cdpa_audit_log')->insert([
                'action_type'  => $action,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'user_id'      => Auth::id(),
                'details'      => $details ? json_encode($details) : null,
                'ip_address'   => request()->ip(),
                'created_at'   => now(),
            ]);
        }
    }

    /**
     * Generate the next reference number for a given table/prefix.
     */
    protected function nextRef(string $table, string $column, string $prefix): string
    {
        $last = DB::table($table)->max($column);
        $seq  = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    // ─── dashboard ────────────────────────────────────────────────────

    public function index()
    {
        $stats = [];

        if (Schema::hasTable('cdpa_processing_activity')) {
            $stats['processing_total']  = DB::table('cdpa_processing_activity')->count();
            $stats['processing_active'] = DB::table('cdpa_processing_activity')->where('is_active', 1)->count();
        }
        if (Schema::hasTable('cdpa_consent')) {
            $stats['consent_total']  = DB::table('cdpa_consent')->count();
            $stats['consent_active'] = DB::table('cdpa_consent')->where('is_active', 1)->whereNull('withdrawal_date')->count();
        }
        if (Schema::hasTable('cdpa_data_subject_request')) {
            $stats['requests_total']   = DB::table('cdpa_data_subject_request')->count();
            $stats['requests_pending'] = DB::table('cdpa_data_subject_request')->where('status', 'pending')->count();
            $stats['requests_overdue'] = DB::table('cdpa_data_subject_request')
                ->where('status', '!=', 'completed')
                ->where('due_date', '<', now()->toDateString())
                ->count();
        }
        if (Schema::hasTable('cdpa_dpia')) {
            $stats['dpia_total'] = DB::table('cdpa_dpia')->count();
            $stats['dpia_due']   = DB::table('cdpa_dpia')
                ->where('next_review_date', '<=', now()->addDays(30)->toDateString())
                ->where('status', '!=', 'completed')
                ->count();
        }
        if (Schema::hasTable('cdpa_breach')) {
            $stats['breaches_total'] = DB::table('cdpa_breach')->count();
            $stats['breaches_open']  = DB::table('cdpa_breach')->where('status', '!=', 'closed')->count();
        }
        if (Schema::hasTable('cdpa_dpo')) {
            $stats['dpo_active'] = DB::table('cdpa_dpo')->where('is_active', 1)->count();
        }
        if (Schema::hasTable('cdpa_controller_license')) {
            $stats['license_active'] = DB::table('cdpa_controller_license')->where('status', 'active')->count();
            $stats['license_expiring'] = DB::table('cdpa_controller_license')
                ->where('status', 'active')
                ->where('expiry_date', '<=', now()->addDays(60)->toDateString())
                ->count();
        }

        $recentAudit = [];
        if (Schema::hasTable('cdpa_audit_log')) {
            $recentAudit = DB::table('cdpa_audit_log')
                ->orderByDesc('created_at')
                ->limit(15)
                ->get();
        }

        return view('cdpa::index', compact('stats', 'recentAudit'));
    }

    // ─── config ───────────────────────────────────────────────────────

    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            $settings = $request->except(['_token']);

            foreach ($settings as $key => $value) {
                DB::table('cdpa_config')->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'updated_at'    => now(),
                    ]
                );
            }

            $this->audit('config_updated', 'cdpa_config', null, $settings);

            return redirect()->route('ahgcdpa.config')
                ->with('success', 'CDPA configuration saved.');
        }

        $settings = [];
        if (Schema::hasTable('cdpa_config')) {
            $settings = DB::table('cdpa_config')
                ->orderBy('setting_key')
                ->get()
                ->keyBy('setting_key');
        }

        return view('cdpa::config', compact('settings'));
    }

    // ─── DPO ──────────────────────────────────────────────────────────

    public function dpo()
    {
        $dpos = DB::table('cdpa_dpo')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('cdpa::dpo', compact('dpos'));
    }

    public function dpoEdit(Request $request)
    {
        $id  = $request->query('id');
        $dpo = $id ? DB::table('cdpa_dpo')->where('id', $id)->first() : null;

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name'               => 'required|string|max:255',
                'email'              => 'required|email|max:255',
                'phone'              => 'nullable|string|max:50',
                'qualifications'     => 'nullable|string',
                'hit_cert_number'    => 'nullable|string|max:100',
                'appointment_date'   => 'required|date',
                'term_end_date'      => 'nullable|date|after_or_equal:appointment_date',
                'form_dp2_submitted' => 'nullable|boolean',
                'form_dp2_date'      => 'nullable|date',
                'form_dp2_ref'       => 'nullable|string|max:100',
                'is_active'          => 'nullable|boolean',
            ]);

            $validated['form_dp2_submitted'] = $request->has('form_dp2_submitted') ? 1 : 0;
            $validated['is_active']          = $request->has('is_active') ? 1 : 0;
            $validated['updated_at']         = now();

            if ($id) {
                DB::table('cdpa_dpo')->where('id', $id)->update($validated);
                $this->audit('dpo_updated', 'cdpa_dpo', (int) $id, $validated);
                $message = 'DPO record updated.';
            } else {
                $validated['created_at'] = now();
                $newId = DB::table('cdpa_dpo')->insertGetId($validated);
                $this->audit('dpo_created', 'cdpa_dpo', $newId, $validated);
                $message = 'DPO record created.';
            }

            return redirect()->route('ahgcdpa.dpo')->with('success', $message);
        }

        return view('cdpa::dpo-edit', compact('dpo'));
    }

    // ─── processing activities ────────────────────────────────────────

    public function processing(Request $request)
    {
        $query = DB::table('cdpa_processing_activity');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $activities = $query->orderByDesc('updated_at')->paginate(25);

        $categories = DB::table('cdpa_processing_activity')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('cdpa::processing', compact('activities', 'categories'));
    }

    public function processingCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name'                   => 'required|string|max:255',
                'category'               => 'required|string|max:100',
                'data_types'             => 'required|string',
                'purpose'                => 'required|string',
                'legal_basis'            => 'required|string|max:94',
                'storage_location'       => 'nullable|string|max:37',
                'international_country'  => 'nullable|string|max:100',
                'retention_period'       => 'nullable|string|max:100',
                'safeguards'             => 'nullable|string',
                'cross_border'           => 'nullable|boolean',
                'cross_border_safeguards'=> 'nullable|string',
                'automated_decision'     => 'nullable|boolean',
                'children_data'          => 'nullable|boolean',
                'biometric_data'         => 'nullable|boolean',
                'health_data'            => 'nullable|boolean',
                'is_active'              => 'nullable|boolean',
            ]);

            $validated['cross_border']       = $request->has('cross_border') ? 1 : 0;
            $validated['automated_decision'] = $request->has('automated_decision') ? 1 : 0;
            $validated['children_data']      = $request->has('children_data') ? 1 : 0;
            $validated['biometric_data']     = $request->has('biometric_data') ? 1 : 0;
            $validated['health_data']        = $request->has('health_data') ? 1 : 0;
            $validated['is_active']          = $request->has('is_active') ? 1 : 0;
            $validated['created_by']         = Auth::id();
            $validated['created_at']         = now();
            $validated['updated_at']         = now();

            $newId = DB::table('cdpa_processing_activity')->insertGetId($validated);
            $this->audit('processing_created', 'cdpa_processing_activity', $newId, $validated);

            return redirect()->route('ahgcdpa.processing')
                ->with('success', 'Processing activity created.');
        }

        return view('cdpa::processing-create');
    }

    public function processingEdit(Request $request)
    {
        $id       = $request->query('id');
        $activity = $id ? DB::table('cdpa_processing_activity')->where('id', $id)->first() : null;

        if (!$activity) {
            return redirect()->route('ahgcdpa.processing')
                ->with('error', 'Processing activity not found.');
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name'                   => 'required|string|max:255',
                'category'               => 'required|string|max:100',
                'data_types'             => 'required|string',
                'purpose'                => 'required|string',
                'legal_basis'            => 'required|string|max:94',
                'storage_location'       => 'nullable|string|max:37',
                'international_country'  => 'nullable|string|max:100',
                'retention_period'       => 'nullable|string|max:100',
                'safeguards'             => 'nullable|string',
                'cross_border'           => 'nullable|boolean',
                'cross_border_safeguards'=> 'nullable|string',
                'automated_decision'     => 'nullable|boolean',
                'children_data'          => 'nullable|boolean',
                'biometric_data'         => 'nullable|boolean',
                'health_data'            => 'nullable|boolean',
                'is_active'              => 'nullable|boolean',
            ]);

            $validated['cross_border']       = $request->has('cross_border') ? 1 : 0;
            $validated['automated_decision'] = $request->has('automated_decision') ? 1 : 0;
            $validated['children_data']      = $request->has('children_data') ? 1 : 0;
            $validated['biometric_data']     = $request->has('biometric_data') ? 1 : 0;
            $validated['health_data']        = $request->has('health_data') ? 1 : 0;
            $validated['is_active']          = $request->has('is_active') ? 1 : 0;
            $validated['updated_at']         = now();

            DB::table('cdpa_processing_activity')->where('id', $id)->update($validated);
            $this->audit('processing_updated', 'cdpa_processing_activity', (int) $id, $validated);

            return redirect()->route('ahgcdpa.processing')
                ->with('success', 'Processing activity updated.');
        }

        return view('cdpa::processing-edit', compact('activity'));
    }

    // ─── consent ──────────────────────────────────────────────────────

    public function consent(Request $request)
    {
        $query = DB::table('cdpa_consent')
            ->leftJoin('cdpa_processing_activity', 'cdpa_consent.processing_activity_id', '=', 'cdpa_processing_activity.id')
            ->select('cdpa_consent.*', 'cdpa_processing_activity.name as activity_name');

        if ($request->filled('is_active')) {
            $query->where('cdpa_consent.is_active', $request->input('is_active'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('cdpa_consent.data_subject_name', 'like', $search)
                  ->orWhere('cdpa_consent.data_subject_email', 'like', $search)
                  ->orWhere('cdpa_consent.purpose', 'like', $search);
            });
        }

        $consents = $query->orderByDesc('cdpa_consent.consent_date')->paginate(25);

        return view('cdpa::consent', compact('consents'));
    }

    // ─── data subject requests ────────────────────────────────────────

    public function requests(Request $request)
    {
        $query = DB::table('cdpa_data_subject_request');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('request_type')) {
            $query->where('request_type', $request->input('request_type'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('data_subject_name', 'like', $search)
                  ->orWhere('reference_number', 'like', $search)
                  ->orWhere('data_subject_email', 'like', $search);
            });
        }

        $requests = $query->orderByDesc('request_date')->paginate(25);

        $statuses = DB::table('cdpa_data_subject_request')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $types = DB::table('cdpa_data_subject_request')
            ->select('request_type')
            ->distinct()
            ->orderBy('request_type')
            ->pluck('request_type');

        return view('cdpa::requests', compact('requests', 'statuses', 'types'));
    }

    public function requestCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'request_type'           => 'required|string|max:69',
                'data_subject_name'      => 'required|string|max:255',
                'data_subject_email'     => 'nullable|email|max:255',
                'data_subject_phone'     => 'nullable|string|max:50',
                'data_subject_id_number' => 'nullable|string|max:50',
                'request_date'           => 'required|date',
                'due_date'               => 'required|date|after_or_equal:request_date',
                'description'            => 'nullable|string',
                'verification_method'    => 'nullable|string|max:100',
            ]);

            $validated['reference_number'] = $this->nextRef('cdpa_data_subject_request', 'reference_number', 'DSR-');
            $validated['status']           = 'pending';
            $validated['handled_by']       = Auth::id();
            $validated['created_at']       = now();
            $validated['updated_at']       = now();

            $newId = DB::table('cdpa_data_subject_request')->insertGetId($validated);
            $this->audit('request_created', 'cdpa_data_subject_request', $newId, $validated);

            return redirect()->route('ahgcdpa.requests')
                ->with('success', 'Data subject request created. Ref: ' . $validated['reference_number']);
        }

        return view('cdpa::request-create');
    }

    public function requestView(Request $request)
    {
        $id  = $request->query('id');
        $dsr = DB::table('cdpa_data_subject_request')->where('id', $id)->first();

        if (!$dsr) {
            return redirect()->route('ahgcdpa.requests')
                ->with('error', 'Data subject request not found.');
        }

        // Handle status update via POST
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'status'           => 'required|string|max:57',
                'response_notes'   => 'nullable|string',
                'rejection_reason' => 'nullable|string',
                'extension_reason' => 'nullable|string',
                'completed_date'   => 'nullable|date',
            ]);

            if ($validated['status'] === 'completed' && empty($validated['completed_date'])) {
                $validated['completed_date'] = now()->toDateString();
            }

            $validated['updated_at'] = now();

            DB::table('cdpa_data_subject_request')->where('id', $id)->update($validated);
            $this->audit('request_updated', 'cdpa_data_subject_request', (int) $id, $validated);

            return redirect()->route('ahgcdpa.request-view', ['id' => $id])
                ->with('success', 'Request updated.');
        }

        $auditTrail = DB::table('cdpa_audit_log')
            ->where('entity_type', 'cdpa_data_subject_request')
            ->where('entity_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return view('cdpa::request-view', compact('dsr', 'auditTrail'));
    }

    // ─── DPIA ─────────────────────────────────────────────────────────

    public function dpia(Request $request)
    {
        $query = DB::table('cdpa_dpia')
            ->leftJoin('cdpa_processing_activity', 'cdpa_dpia.processing_activity_id', '=', 'cdpa_processing_activity.id')
            ->select('cdpa_dpia.*', 'cdpa_processing_activity.name as activity_name');

        if ($request->filled('status')) {
            $query->where('cdpa_dpia.status', $request->input('status'));
        }
        if ($request->filled('risk_level')) {
            $query->where('cdpa_dpia.risk_level', $request->input('risk_level'));
        }

        $dpias = $query->orderByDesc('cdpa_dpia.assessment_date')->paginate(25);

        $statuses = DB::table('cdpa_dpia')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('cdpa::dpia', compact('dpias', 'statuses'));
    }

    public function dpiaCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name'                    => 'required|string|max:255',
                'processing_activity_id'  => 'nullable|integer|exists:cdpa_processing_activity,id',
                'description'             => 'nullable|string',
                'necessity_assessment'    => 'nullable|string',
                'risk_level'              => 'nullable|string|max:34',
                'assessment_date'         => 'required|date',
                'assessor_name'           => 'nullable|string|max:255',
                'next_review_date'        => 'nullable|date|after_or_equal:assessment_date',
                'status'                  => 'nullable|string|max:46',
                'risks_identified'        => 'nullable|string',
                'mitigation_measures'     => 'nullable|string',
                'residual_risk_level'     => 'nullable|string|max:34',
                'dpo_approval'            => 'nullable|boolean',
                'dpo_approval_date'       => 'nullable|date',
                'dpo_comments'            => 'nullable|string',
            ]);

            $validated['dpo_approval'] = $request->has('dpo_approval') ? 1 : 0;
            $validated['status']       = $validated['status'] ?? 'draft';
            $validated['created_by']   = Auth::id();
            $validated['created_at']   = now();
            $validated['updated_at']   = now();

            $newId = DB::table('cdpa_dpia')->insertGetId($validated);
            $this->audit('dpia_created', 'cdpa_dpia', $newId, $validated);

            return redirect()->route('ahgcdpa.dpia')
                ->with('success', 'DPIA created.');
        }

        $activities = DB::table('cdpa_processing_activity')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('cdpa::dpia-create', compact('activities'));
    }

    public function dpiaView(Request $request)
    {
        $id   = $request->query('id');
        $dpia = DB::table('cdpa_dpia')
            ->leftJoin('cdpa_processing_activity', 'cdpa_dpia.processing_activity_id', '=', 'cdpa_processing_activity.id')
            ->select('cdpa_dpia.*', 'cdpa_processing_activity.name as activity_name')
            ->where('cdpa_dpia.id', $id)
            ->first();

        if (!$dpia) {
            return redirect()->route('ahgcdpa.dpia')
                ->with('error', 'DPIA not found.');
        }

        // Handle status / approval update via POST
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'status'              => 'nullable|string|max:46',
                'risk_level'          => 'nullable|string|max:34',
                'residual_risk_level' => 'nullable|string|max:34',
                'risks_identified'    => 'nullable|string',
                'mitigation_measures' => 'nullable|string',
                'dpo_approval'        => 'nullable|boolean',
                'dpo_approval_date'   => 'nullable|date',
                'dpo_comments'        => 'nullable|string',
                'next_review_date'    => 'nullable|date',
            ]);

            $validated['dpo_approval'] = $request->has('dpo_approval') ? 1 : 0;
            $validated['updated_at']   = now();

            DB::table('cdpa_dpia')->where('id', $id)->update($validated);
            $this->audit('dpia_updated', 'cdpa_dpia', (int) $id, $validated);

            return redirect()->route('ahgcdpa.dpia-view', ['id' => $id])
                ->with('success', 'DPIA updated.');
        }

        $auditTrail = DB::table('cdpa_audit_log')
            ->where('entity_type', 'cdpa_dpia')
            ->where('entity_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return view('cdpa::dpia-view', compact('dpia', 'auditTrail'));
    }

    // ─── breaches ─────────────────────────────────────────────────────

    public function breaches(Request $request)
    {
        $query = DB::table('cdpa_breach');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $breaches = $query->orderByDesc('incident_date')->paginate(25);

        $statuses = DB::table('cdpa_breach')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('cdpa::breaches', compact('breaches', 'statuses'));
    }

    public function breachCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'incident_date'          => 'required|date',
                'discovery_date'         => 'required|date',
                'description'            => 'required|string',
                'breach_type'            => 'required|string|max:92',
                'data_affected'          => 'nullable|string',
                'records_affected'       => 'nullable|integer|min:0',
                'data_subjects_affected' => 'nullable|integer|min:0',
                'severity'               => 'nullable|string|max:34',
                'root_cause'             => 'nullable|string',
                'remediation'            => 'nullable|string',
                'prevention_measures'    => 'nullable|string',
            ]);

            $validated['reference_number'] = $this->nextRef('cdpa_breach', 'reference_number', 'BRE-');
            $validated['status']           = 'investigating';
            $validated['severity']         = $validated['severity'] ?? 'medium';
            $validated['reported_by']      = Auth::id();
            $validated['created_at']       = now();
            $validated['updated_at']       = now();

            $newId = DB::table('cdpa_breach')->insertGetId($validated);
            $this->audit('breach_created', 'cdpa_breach', $newId, $validated);

            return redirect()->route('ahgcdpa.breaches')
                ->with('success', 'Breach reported. Ref: ' . $validated['reference_number']);
        }

        return view('cdpa::breach-create');
    }

    public function breachView(Request $request)
    {
        $id     = $request->query('id');
        $breach = DB::table('cdpa_breach')->where('id', $id)->first();

        if (!$breach) {
            return redirect()->route('ahgcdpa.breaches')
                ->with('error', 'Breach record not found.');
        }

        // Handle status / notification update via POST
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'status'                 => 'nullable|string|max:50',
                'potraz_notified'        => 'nullable|boolean',
                'potraz_notified_date'   => 'nullable|date',
                'potraz_reference'       => 'nullable|string|max:100',
                'subjects_notified'      => 'nullable|boolean',
                'subjects_notified_date' => 'nullable|date',
                'notification_method'    => 'nullable|string',
                'root_cause'             => 'nullable|string',
                'remediation'            => 'nullable|string',
                'prevention_measures'    => 'nullable|string',
                'closed_date'            => 'nullable|date',
            ]);

            $validated['potraz_notified']   = $request->has('potraz_notified') ? 1 : 0;
            $validated['subjects_notified'] = $request->has('subjects_notified') ? 1 : 0;

            if ($validated['status'] === 'closed' && empty($validated['closed_date'])) {
                $validated['closed_date'] = now();
            }

            $validated['updated_at'] = now();

            DB::table('cdpa_breach')->where('id', $id)->update($validated);
            $this->audit('breach_updated', 'cdpa_breach', (int) $id, $validated);

            return redirect()->route('ahgcdpa.breach-view', ['id' => $id])
                ->with('success', 'Breach record updated.');
        }

        $auditTrail = DB::table('cdpa_audit_log')
            ->where('entity_type', 'cdpa_breach')
            ->where('entity_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return view('cdpa::breach-view', compact('breach', 'auditTrail'));
    }

    // ─── controller / processor licenses ──────────────────────────────

    public function license()
    {
        $licenses = DB::table('cdpa_controller_license')
            ->orderByDesc('expiry_date')
            ->get();

        return view('cdpa::license', compact('licenses'));
    }

    public function licenseEdit(Request $request)
    {
        $id      = $request->query('id');
        $license = $id ? DB::table('cdpa_controller_license')->where('id', $id)->first() : null;

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'license_number'        => 'required|string|max:100',
                'tier'                  => 'required|string|max:33',
                'organization_name'     => 'required|string|max:255',
                'registration_date'     => 'required|date',
                'issue_date'            => 'required|date',
                'expiry_date'           => 'required|date|after_or_equal:issue_date',
                'potraz_ref'            => 'nullable|string|max:100',
                'certificate_path'      => 'nullable|string|max:500',
                'data_subjects_count'   => 'nullable|integer|min:0',
                'status'                => 'nullable|string|max:50',
                'notes'                 => 'nullable|string',
            ]);

            $validated['renewal_reminder_sent'] = $request->has('renewal_reminder_sent') ? 1 : 0;
            $validated['status']                = $validated['status'] ?? 'active';
            $validated['updated_at']            = now();

            if ($id) {
                DB::table('cdpa_controller_license')->where('id', $id)->update($validated);
                $this->audit('license_updated', 'cdpa_controller_license', (int) $id, $validated);
                $message = 'License updated.';
            } else {
                $validated['created_at'] = now();
                $newId = DB::table('cdpa_controller_license')->insertGetId($validated);
                $this->audit('license_created', 'cdpa_controller_license', $newId, $validated);
                $message = 'License created.';
            }

            return redirect()->route('ahgcdpa.license')->with('success', $message);
        }

        return view('cdpa::license-edit', compact('license'));
    }

    // ─── reports ──────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subYear()->toDateString());
        $dateTo   = $request->input('date_to', now()->toDateString());

        // Processing activities summary
        $processingByCategory = DB::table('cdpa_processing_activity')
            ->select('category', DB::raw('COUNT(*) as total'))
            ->where('is_active', 1)
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $processingByLegalBasis = DB::table('cdpa_processing_activity')
            ->select('legal_basis', DB::raw('COUNT(*) as total'))
            ->where('is_active', 1)
            ->groupBy('legal_basis')
            ->orderByDesc('total')
            ->get();

        // Consent statistics
        $consentStats = [
            'total'     => DB::table('cdpa_consent')->count(),
            'active'    => DB::table('cdpa_consent')->where('is_active', 1)->whereNull('withdrawal_date')->count(),
            'withdrawn' => DB::table('cdpa_consent')->whereNotNull('withdrawal_date')->count(),
            'biometric' => DB::table('cdpa_consent')->where('is_biometric', 1)->count(),
            'children'  => DB::table('cdpa_consent')->where('is_children', 1)->count(),
        ];

        // DSR statistics
        $requestsByType = DB::table('cdpa_data_subject_request')
            ->select('request_type', DB::raw('COUNT(*) as total'))
            ->whereBetween('request_date', [$dateFrom, $dateTo])
            ->groupBy('request_type')
            ->orderByDesc('total')
            ->get();

        $requestsByStatus = DB::table('cdpa_data_subject_request')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $avgResponseDays = DB::table('cdpa_data_subject_request')
            ->whereNotNull('completed_date')
            ->selectRaw('AVG(DATEDIFF(completed_date, request_date)) as avg_days')
            ->value('avg_days');

        // Breach statistics
        $breachesByType = DB::table('cdpa_breach')
            ->select('breach_type', DB::raw('COUNT(*) as total'))
            ->whereBetween('incident_date', [$dateFrom, $dateTo])
            ->groupBy('breach_type')
            ->orderByDesc('total')
            ->get();

        $breachesBySeverity = DB::table('cdpa_breach')
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->orderByDesc('total')
            ->get();

        // DPIA statistics
        $dpiaByStatus = DB::table('cdpa_dpia')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $dpiaByRisk = DB::table('cdpa_dpia')
            ->select('risk_level', DB::raw('COUNT(*) as total'))
            ->groupBy('risk_level')
            ->orderByDesc('total')
            ->get();

        // Special data flags
        $specialDataFlags = [
            'cross_border'      => DB::table('cdpa_processing_activity')->where('cross_border', 1)->where('is_active', 1)->count(),
            'automated_decision'=> DB::table('cdpa_processing_activity')->where('automated_decision', 1)->where('is_active', 1)->count(),
            'children_data'     => DB::table('cdpa_processing_activity')->where('children_data', 1)->where('is_active', 1)->count(),
            'biometric_data'    => DB::table('cdpa_processing_activity')->where('biometric_data', 1)->where('is_active', 1)->count(),
            'health_data'       => DB::table('cdpa_processing_activity')->where('health_data', 1)->where('is_active', 1)->count(),
        ];

        return view('cdpa::reports', compact(
            'dateFrom', 'dateTo',
            'processingByCategory', 'processingByLegalBasis',
            'consentStats',
            'requestsByType', 'requestsByStatus', 'avgResponseDays',
            'breachesByType', 'breachesBySeverity',
            'dpiaByStatus', 'dpiaByRisk',
            'specialDataFlags'
        ));
    }
}
