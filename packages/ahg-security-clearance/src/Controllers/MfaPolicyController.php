<?php

/**
 * MfaPolicyController - admin UI for per-tenant MFA enforcement (#723).
 *
 * Three endpoints:
 *   - index()  -> list every tenant + its effective policy
 *   - edit()   -> form for one tenant (or the global default when id=0)
 *   - update() -> POST handler; validates + writes via MfaPolicyService
 *   - reset()  -> POST handler; deletes the tenant-specific row so the
 *                 tenant falls back to the global default
 *
 * All four sit behind the 'admin' middleware (see routes/web.php). The
 * vocabulary for enforcement is pulled from ahg_dropdown taxonomy
 * 'mfa_enforcement' so we can re-style / re-label values without code
 * changes. The grace period is a free-form 0..365 integer.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Controllers;

use AhgSecurityClearance\Services\MfaPolicyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MfaPolicyController extends Controller
{
    public function __construct(private readonly MfaPolicyService $policy) {}

    public function index(): View
    {
        $global = $this->policy->policyFor(null);
        $tenants = $this->policy->listAllForAdmin();
        $enforcementOptions = $this->loadEnforcementOptions();

        return view('ahg-security-clearance::mfa-policy.index', [
            'global' => $global,
            'tenants' => $tenants,
            'enforcementOptions' => $enforcementOptions,
        ]);
    }

    /**
     * Edit form. tenantId = 0 means "edit the global default row".
     */
    public function edit(int $tenantId): View
    {
        $resolved = $tenantId === 0 ? null : $tenantId;
        $policy = $this->policy->policyFor($resolved);
        $enforcementOptions = $this->loadEnforcementOptions();

        $tenantLabel = $resolved === null
            ? __('Global default')
            : $this->tenantDisplayName($resolved);

        return view('ahg-security-clearance::mfa-policy.edit', [
            'tenantId' => $resolved,
            'tenantLabel' => $tenantLabel,
            'policy' => $policy,
            'enforcementOptions' => $enforcementOptions,
        ]);
    }

    public function update(Request $request, int $tenantId): RedirectResponse
    {
        $resolved = $tenantId === 0 ? null : $tenantId;

        $validated = $request->validate([
            'enforcement' => 'required|string|in:'.implode(',', MfaPolicyService::VALID_ENFORCEMENTS),
            'grace_period_days' => 'required|integer|min:0|max:365',
        ]);

        $this->policy->setPolicy(
            $resolved,
            (string) $validated['enforcement'],
            (int) $validated['grace_period_days']
        );

        return redirect()
            ->route('security-clearance.mfa-policy.index')
            ->with('success', __('MFA policy saved.'));
    }

    public function reset(int $tenantId): RedirectResponse
    {
        if ($tenantId <= 0) {
            return redirect()
                ->route('security-clearance.mfa-policy.index')
                ->with('error', __('The global default policy cannot be reset to itself.'));
        }

        $this->policy->resetToGlobalDefault($tenantId);

        return redirect()
            ->route('security-clearance.mfa-policy.index')
            ->with('success', __('Tenant reverted to global default policy.'));
    }

    /**
     * Read enforcement vocabulary from ahg_dropdown taxonomy 'mfa_enforcement'.
     * Falls back to a hardcoded list (with English labels) when the table or
     * rows are not present yet - the service-provider seed catches up on the
     * next boot.
     */
    private function loadEnforcementOptions(): array
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return $this->fallbackEnforcementOptions();
        }

        $rows = DB::table('ahg_dropdown')
            ->where('taxonomy', 'mfa_enforcement')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'label', 'color', 'icon']);

        if ($rows->isEmpty()) {
            return $this->fallbackEnforcementOptions();
        }

        return $rows->map(fn ($r) => [
            'code' => (string) $r->code,
            'label' => (string) $r->label,
            'color' => $r->color,
            'icon' => $r->icon,
        ])->all();
    }

    private function fallbackEnforcementOptions(): array
    {
        return [
            ['code' => 'off', 'label' => 'Off', 'color' => '#6c757d', 'icon' => 'slash-circle'],
            ['code' => 'optional', 'label' => 'Optional', 'color' => '#0d6efd', 'icon' => 'circle'],
            ['code' => 'required_for_admins', 'label' => 'Required for admins', 'color' => '#fd7e14', 'icon' => 'shield-lock'],
            ['code' => 'required', 'label' => 'Required for everyone', 'color' => '#dc3545', 'icon' => 'shield-fill-check'],
        ];
    }

    private function tenantDisplayName(int $tenantId): string
    {
        if (! Schema::hasTable('ahg_tenant')) {
            return "Tenant #{$tenantId}";
        }
        $row = DB::table('ahg_tenant')->where('id', $tenantId)->first(['name', 'code']);

        return $row ? (string) ($row->name ?: $row->code) : "Tenant #{$tenantId}";
    }
}
