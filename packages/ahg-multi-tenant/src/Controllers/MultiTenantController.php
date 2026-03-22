<?php

namespace AhgMultiTenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MultiTenantController extends Controller
{
    public function create() { return view('statistics::create'); }

    public function editTenant() { return view('statistics::edit-tenant'); }

    public function index() { return view('statistics::index'); }

    public function superUsers() { return view('statistics::super-users'); }

    public function unknownDomain() { return view('statistics::unknown-domain'); }

    public function unknownTenant() { return view('statistics::unknown-tenant'); }

    /**
     * Admin dashboard for multi-tenant management.
     */
    public function admin()
    {
        return view('ahg-multi-tenant::admin');
    }

    /**
     * Manage branding for a tenant.
     */
    public function branding(Request $request)
    {
        $tenantId = (int) $request->get('tenant_id');

        return view('ahg-multi-tenant::branding', compact('tenantId'));
    }

    /**
     * Handle POST actions for multi-tenant management.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');

        return redirect()->back()->with('notice', 'Action processed.');
    }

    /**
     * Manage users for a tenant.
     */
    public function users(Request $request)
    {
        $tenantId = (int) $request->get('tenant_id');

        return view('ahg-multi-tenant::users', compact('tenantId'));
    }
}
