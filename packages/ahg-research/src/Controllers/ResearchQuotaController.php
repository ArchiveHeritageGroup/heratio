<?php

/**
 * ResearchQuotaController - Controller for Heratio
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
use AhgResearch\Services\ResearchQuotaService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ResearchQuotaController - Admin quota management screen (#1325).
 *
 * Surfaces the per-researcher usage-vs-limit dashboard (computed by
 * ResearchQuotaService->usageReport) alongside a CRUD over the
 * research_quota_policy table. Sits behind the 'admin' middleware group.
 *
 * Scope and period option lists are read from the Dropdown Manager
 * (ahg_dropdown taxonomies quota_scope / quota_period) with a hardcoded
 * fallback so the page never renders empty selects. Policy rows are keyed
 * on (scope, scope_key, period) for the upsert.
 */
class ResearchQuotaController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchQuotaService $quota;

    protected ResearchService $service;

    public function __construct(ResearchQuotaService $quota, ResearchService $service)
    {
        $this->quota = $quota;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $usage = $this->quota->usageReport(200);

        $policies = Schema::hasTable('research_quota_policy')
            ? DB::table('research_quota_policy')
                ->orderBy('scope')
                ->orderBy('scope_key')
                ->orderBy('period')
                ->get()
            : collect();

        $scopeOptions = $this->dropdownOptions('quota_scope', [
            ['code' => 'global',  'label' => 'Global (all researchers)'],
            ['code' => 'role',    'label' => 'Role (researcher type)'],
            ['code' => 'user',    'label' => 'User (single researcher)'],
            ['code' => 'project', 'label' => 'Project'],
        ]);

        $periodOptions = $this->dropdownOptions('quota_period', [
            ['code' => 'monthly', 'label' => 'Monthly (calendar month)'],
            ['code' => 'total',   'label' => 'Total (all-time)'],
        ]);

        return view('research::research.admin-quotas', array_merge(
            $this->getSidebarData('adminQuotas'),
            compact('usage', 'policies', 'scopeOptions', 'periodOptions')
        ));
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $action = $request->input('form_action');

        if ($action === 'delete') {
            $policyId = (int) $request->input('policy_id');
            DB::table('research_quota_policy')->where('id', $policyId)->delete();
            $this->logResearchActivity('delete', 'quota_policy', $policyId, null, ['method' => 'ResearchQuotaController@store']);

            return redirect()->route('research.adminQuotas')->with('success', 'Quota policy deleted.');
        }

        $scopeCodes  = $this->codeList('quota_scope', ['global', 'role', 'user', 'project']);
        $periodCodes = $this->codeList('quota_period', ['monthly', 'total']);

        $validated = $request->validate([
            'scope'             => 'required|string|in:' . implode(',', $scopeCodes),
            'scope_key'         => 'nullable|string|max:191',
            'period'            => 'required|string|in:' . implode(',', $periodCodes),
            'max_downloads'     => 'nullable|integer|min:0',
            'max_storage_bytes' => 'nullable|integer|min:0',
            'soft_warn_pct'     => 'required|integer|min:1|max:100',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $scope    = $validated['scope'];
        $scopeKey = $scope === 'global' ? '*' : (trim((string) ($validated['scope_key'] ?? '')) ?: '*');
        $period   = $validated['period'];

        $data = [
            'scope'             => $scope,
            'scope_key'         => $scopeKey,
            'period'            => $period,
            'max_downloads'     => $validated['max_downloads'] !== null && $validated['max_downloads'] !== '' ? (int) $validated['max_downloads'] : null,
            'max_storage_bytes' => $validated['max_storage_bytes'] !== null && $validated['max_storage_bytes'] !== '' ? (int) $validated['max_storage_bytes'] : null,
            'soft_warn_pct'     => (int) $validated['soft_warn_pct'],
            'is_active'         => $request->has('is_active') ? 1 : 0,
            'notes'             => $validated['notes'] ?? null,
            'updated_at'        => now(),
        ];

        // Upsert keyed on (scope, scope_key, period).
        $existing = DB::table('research_quota_policy')
            ->where('scope', $scope)
            ->where('scope_key', $scopeKey)
            ->where('period', $period)
            ->first();

        if ($existing) {
            DB::table('research_quota_policy')->where('id', $existing->id)->update($data);
            $this->logResearchActivity('update', 'quota_policy', (int) $existing->id, $scope.':'.$scopeKey, ['method' => 'ResearchQuotaController@store', 'period' => $period]);

            return redirect()->route('research.adminQuotas')->with('success', 'Quota policy updated.');
        }

        $data['created_at'] = now();
        $newId = (int) DB::table('research_quota_policy')->insertGetId($data);
        $this->logResearchActivity('create', 'quota_policy', $newId, $scope.':'.$scopeKey, ['method' => 'ResearchQuotaController@store', 'period' => $period]);

        return redirect()->route('research.adminQuotas')->with('success', 'Quota policy created.');
    }

    // --- internals --------------------------------------------------------

    /**
     * Dropdown option rows {code,label} from ahg_dropdown, falling back to the
     * supplied hardcoded list when the table/rows are missing.
     *
     * @param  array<int,array{code:string,label:string}>  $fallback
     * @return array<int,array{code:string,label:string}>
     */
    private function dropdownOptions(string $taxonomy, array $fallback): array
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', $taxonomy)
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->get(['code', 'label']);
                if ($rows->isNotEmpty()) {
                    return $rows->map(fn ($r) => ['code' => $r->code, 'label' => $r->label])->all();
                }
            }
        } catch (\Throwable $e) {
            // fall through to fallback
        }

        return $fallback;
    }

    /**
     * Just the codes from a dropdown taxonomy (for in: validation), with fallback.
     *
     * @param  array<int,string>  $fallback
     * @return array<int,string>
     */
    private function codeList(string $taxonomy, array $fallback): array
    {
        $opts = $this->dropdownOptions(
            $taxonomy,
            array_map(fn ($c) => ['code' => $c, 'label' => $c], $fallback)
        );

        return array_values(array_unique(array_map(fn ($o) => $o['code'], $opts)));
    }
}
