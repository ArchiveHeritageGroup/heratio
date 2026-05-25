<?php

/**
 * TenantEmailBranding
 *
 * Phase 2 of #674 (Email + notifications). Resolves the tenant-specific
 * email branding row (logo, colours, footer html, sender name + override)
 * for the active tenant of the current request. Used by the email layout
 * template via @inject:
 *
 *   @inject('branding', \App\Services\TenantEmailBranding::class)
 *   <img src="{{ $branding->logoUrl() }}">
 *   <style>a{color:{{ $branding->primaryColor() }}}</style>
 *
 * Resolution order:
 *   1. $this->tenantId (set explicitly by the dispatching Mailable when
 *      the request is queued and the session is gone)
 *   2. AhgMultiTenant\Services\TenantService::getCurrentTenant() (web
 *      request context)
 *   3. Heratio defaults (config('mail.from'), brand-neutral colours)
 *
 * The class is deliberately defensive: when ahg_tenant_email_branding /
 * ahg_tenant tables don't exist (fresh install, tests, etc) it returns
 * sensible Heratio defaults rather than crashing the mail render.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantEmailBranding
{
    public ?int $tenantId = null;

    private ?object $row = null;

    private bool $loaded = false;

    public function forTenant(?int $tenantId): self
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;
        $clone->row = null;
        $clone->loaded = false;

        return $clone;
    }

    public function logoUrl(): ?string
    {
        return $this->load()?->logo_url ?? null;
    }

    public function primaryColor(): string
    {
        return (string) ($this->load()?->primary_color ?? '#0d6efd');
    }

    public function secondaryColor(): string
    {
        return (string) ($this->load()?->secondary_color ?? '#6c757d');
    }

    public function footerHtml(): string
    {
        $custom = $this->load()?->footer_text_html ?? null;
        if ($custom !== null && trim((string) $custom) !== '') {
            return (string) $custom;
        }

        return '<p style="margin:0;">This is an automated message. Please do not reply to this email.</p>';
    }

    public function senderName(): ?string
    {
        return $this->load()?->sender_name ?? config('mail.from.name');
    }

    public function senderEmail(): ?string
    {
        return $this->load()?->sender_email_override ?? config('mail.from.address');
    }

    public function tenantName(): string
    {
        $tenantId = $this->resolvedTenantId();
        if ($tenantId === null) {
            return (string) (config('app.name') ?: 'Heratio');
        }

        try {
            $name = DB::table('ahg_tenant')->where('id', $tenantId)->value('name');

            return (string) ($name ?? config('app.name') ?: 'Heratio');
        } catch (\Throwable $e) {
            return (string) (config('app.name') ?: 'Heratio');
        }
    }

    private function load(): ?object
    {
        if ($this->loaded) {
            return $this->row;
        }
        $this->loaded = true;

        $tenantId = $this->resolvedTenantId();
        if ($tenantId === null) {
            return $this->row = null;
        }

        if (! Schema::hasTable('ahg_tenant_email_branding')) {
            return $this->row = null;
        }

        try {
            $this->row = DB::table('ahg_tenant_email_branding')
                ->where('tenant_id', $tenantId)
                ->first();
        } catch (\Throwable $e) {
            $this->row = null;
        }

        return $this->row;
    }

    private function resolvedTenantId(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        // Best-effort resolution via TenantService when available.
        try {
            if (class_exists(\AhgMultiTenant\Services\TenantService::class)) {
                /** @var \AhgMultiTenant\Services\TenantService $svc */
                $svc = app(\AhgMultiTenant\Services\TenantService::class);
                $tenant = $svc->getCurrentTenant();
                if ($tenant && isset($tenant->id)) {
                    return (int) $tenant->id;
                }
            }
        } catch (\Throwable $e) {
            // ignore - branding is decorative, not critical
        }

        return null;
    }
}
