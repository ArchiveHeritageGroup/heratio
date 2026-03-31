<?php

/**
 * MultiTenantController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
