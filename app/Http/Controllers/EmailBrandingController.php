<?php

/**
 * EmailBrandingController
 *
 * Phase 2 of #674. Admin UI at /admin/email/branding for managing per-tenant
 * email branding (logo url, colours, footer html, sender name + override).
 * One row per tenant in ahg_tenant_email_branding.
 *
 * Visible only to admins (route middleware: auth.required + admin).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EmailBrandingController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = $this->tenants();

        $selectedTenantId = (int) ($request->query('tenant_id', 0));
        if ($selectedTenantId === 0 && $tenants->isNotEmpty()) {
            $selectedTenantId = (int) $tenants->first()->id;
        }

        $row = null;
        if ($selectedTenantId > 0 && Schema::hasTable('ahg_tenant_email_branding')) {
            $row = DB::table('ahg_tenant_email_branding')
                ->where('tenant_id', $selectedTenantId)
                ->first();
        }

        return view('admin.email-branding', [
            'tenants' => $tenants,
            'selectedTenantId' => $selectedTenantId,
            'row' => $row,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('ahg_tenant_email_branding')) {
            return back()->with('error', 'Email branding table is not installed; run migrations.');
        }

        $tenantId = (int) $request->input('tenant_id', 0);
        if ($tenantId <= 0) {
            return back()->withErrors(['tenant_id' => 'Tenant is required.']);
        }

        $validated = $request->validate([
            'logo_url' => 'nullable|string|max:500|url',
            'primary_color' => 'nullable|string|max:20|regex:/^#[A-Fa-f0-9]{3,8}$/',
            'secondary_color' => 'nullable|string|max:20|regex:/^#[A-Fa-f0-9]{3,8}$/',
            'footer_text_html' => 'nullable|string|max:8000',
            'sender_name' => 'nullable|string|max:255',
            'sender_email_override' => 'nullable|email|max:255',
        ]);

        $payload = array_merge(
            $validated,
            ['updated_at' => now()]
        );

        DB::table('ahg_tenant_email_branding')->updateOrInsert(
            ['tenant_id' => $tenantId],
            $payload
        );

        return redirect()
            ->route('admin.email.branding', ['tenant_id' => $tenantId])
            ->with('success', __('Email branding saved.'));
    }

    private function tenants(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('ahg_tenant')) {
            return collect();
        }
        try {
            return DB::table('ahg_tenant')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->select('id', 'name', 'code', 'is_default')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
