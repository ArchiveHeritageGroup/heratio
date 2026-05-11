<?php

/**
 * TenantController - admin CRUD + tenant switcher endpoint.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Controllers;

use AhgMultiTenant\Services\TenantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    private const ACCEPTED_FIELDS = [
        'name', 'code', 'description', 'domain', 'subdomain',
        'repository_id', 'contact_email', 'contact_phone',
        'max_users', 'max_storage_gb', 'is_active', 'is_default',
        'status',
    ];

    protected TenantService $service;

    public function __construct()
    {
        $this->service = new TenantService();
    }

    public function index()
    {
        $tenants = $this->service->getTenants();

        return view('ahg-multi-tenant::index', compact('tenants'));
    }

    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->service->createTenant($request->only(self::ACCEPTED_FIELDS));

            return redirect()->route('tenant.index')->with('notice', __('Tenant created'));
        }

        return view('ahg-multi-tenant::create');
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $this->service->getTenant($id);
        abort_unless($tenant, 404, 'Tenant not found');

        if ($request->isMethod('post')) {
            $this->service->updateTenant($id, $request->only(self::ACCEPTED_FIELDS));

            return redirect()->route('tenant.index')->with('notice', __('Tenant updated'));
        }

        return view('ahg-multi-tenant::edit-tenant', compact('tenant'));
    }

    public function superUsers()
    {
        $users = $this->service->getSuperUsers();

        return view('ahg-multi-tenant::super-users', compact('users'));
    }

    public function users(int $tenantId)
    {
        $tenant = $this->service->getTenant($tenantId);
        abort_unless($tenant, 404, 'Tenant not found');

        $users = $this->service->getTenantUsers($tenantId);

        return view('ahg-multi-tenant::users', compact('tenant', 'users'));
    }

    public function branding(Request $request, int $tenantId)
    {
        $tenant = $this->service->getTenant($tenantId);
        abort_unless($tenant, 404, 'Tenant not found');

        if ($request->isMethod('post')) {
            $this->service->updateBranding($tenantId, $request->only([
                'logo_url', 'primary_color', 'secondary_color',
                'header_bg_color', 'header_text_color', 'link_color', 'button_color',
                'header_html', 'footer_html', 'custom_css',
            ]));

            return redirect()->route('tenant.branding', $tenantId)->with('notice', __('Branding updated'));
        }

        $branding = $this->service->getBranding($tenantId);

        return view('ahg-multi-tenant::branding', compact('tenant', 'branding'));
    }

    public function destroy(int $id)
    {
        $this->service->deleteTenant($id);

        return redirect()->route('tenant.index')->with('notice', __('Tenant deleted'));
    }

    public function unknownDomain()
    {
        return view('ahg-multi-tenant::unknown-domain');
    }

    public function unknownTenant()
    {
        return view('ahg-multi-tenant::unknown-tenant');
    }

    /**
     * JSON endpoint backing the navbar tenant switcher.
     */
    public function switcher()
    {
        return response()->json([
            'tenants' => $this->service->getTenants(),
            'current' => $this->service->getCurrentTenant(),
        ]);
    }

    /**
     * POST /tenant/switch  -  set the current tenant for this session.
     */
    public function switchTo(Request $request)
    {
        $id = (int) $request->input('tenant_id');
        $tenant = $this->service->getTenant($id);

        if (!$tenant || !$tenant->is_active) {
            return redirect()->back()->with('error', __('Tenant not available'));
        }

        // Only allow the switch if the user is assigned to the tenant or is
        // a Laravel admin. AclService remains the source of truth elsewhere.
        $userId = (int) (auth()->id() ?? 0);
        $isAssigned = $userId > 0 && \Illuminate\Support\Facades\DB::table('ahg_tenant_user')
            ->where('tenant_id', $id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isAssigned && !(auth()->user()->is_admin ?? false)) {
            return redirect()->back()->with('error', __('Not authorised for that tenant'));
        }

        session(['current_tenant_id' => $id]);

        return redirect()->back()->with('notice', __('Switched to :name', ['name' => $tenant->name]));
    }
}
