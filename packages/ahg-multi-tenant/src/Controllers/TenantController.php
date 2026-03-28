<?php

namespace AhgMultiTenant\Controllers;

use AhgMultiTenant\Services\TenantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantController extends Controller
{
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
            $id = $this->service->createTenant($request->only([
                'name', 'code', 'domain', 'database_name',
                'description', 'contact_email', 'contact_phone',
                'max_users', 'max_storage_gb', 'is_active',
            ]));

            return redirect()->route('tenant.index')->with('notice', 'Tenant created');
        }

        return view('ahg-multi-tenant::create');
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $this->service->getTenant($id);
        abort_unless($tenant, 404, 'Tenant not found');

        if ($request->isMethod('post')) {
            $this->service->updateTenant($id, $request->only([
                'name', 'code', 'domain', 'database_name',
                'description', 'contact_email', 'contact_phone',
                'max_users', 'max_storage_gb', 'is_active',
            ]));

            return redirect()->route('tenant.index')->with('notice', 'Tenant updated');
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
                'header_html', 'footer_html', 'custom_css',
            ]));

            return redirect()->route('tenant.branding', $tenantId)->with('notice', 'Branding updated');
        }

        $branding = $this->service->getBranding($tenantId);

        return view('ahg-multi-tenant::branding', compact('tenant', 'branding'));
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
     * Admin dashboard for tenant management.
     */
    public function admin()
    {
        $tenants = $this->service->getTenants();
        $stats = [
            'total' => count($tenants),
            'active' => collect($tenants)->where('is_active', 1)->count(),
        ];

        return view('ahg-multi-tenant::admin', compact('tenants', 'stats'));
    }

    /**
     * Handle POST actions for tenant management.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');
        $id = (int) $request->get('id');

        if ($action === 'delete' && $id) {
            $this->service->deleteTenant($id);

            return redirect()->route('tenant.index')->with('notice', 'Tenant deleted.');
        }

        if ($action === 'toggle_active' && $id) {
            $tenant = $this->service->getTenant($id);
            if ($tenant) {
                $this->service->updateTenant($id, ['is_active' => !$tenant->is_active]);
            }

            return redirect()->route('tenant.index')->with('notice', 'Tenant status updated.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    /**
     * Return available tenants as JSON for the tenant switcher dropdown.
     */
    public function switcher()
    {
        $tenants = $this->service->getTenants();
        $current = $this->service->getCurrentTenant();

        return response()->json([
            'tenants' => $tenants,
            'current' => $current,
        ]);
    }
}
