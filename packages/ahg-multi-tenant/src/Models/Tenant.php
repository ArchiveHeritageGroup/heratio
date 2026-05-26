<?php

/**
 * Tenant - Eloquent model over ahg_tenant.
 *
 * Heratio is single-DB / repository-scoped multi-tenant. Each ahg_tenant
 * row maps optionally to a `repository` via repository_id; data isolation
 * flows through that FK on information_object / digital_object / etc.
 *
 * The brief for issue #651 mentions a `tenants` table; the existing
 * Heratio schema uses `ahg_tenant` (richer - already wired to repository,
 * branding, settings overrides, and ahg-acl). We bind the model to the
 * existing table rather than fork the schema.
 *
 * Phase 1 expectations - this model is a thin data access shim only.
 * Behaviour lives in TenantContext + TenantService.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $table = 'ahg_tenant';

    protected $fillable = [
        'code',
        'name',
        'description',
        'domain',
        'subdomain',
        'repository_id',
        'contact_email',
        'contact_phone',
        'max_users',
        'max_storage_gb',
        'is_active',
        'is_default',
        'status',
        'trial_ends_at',
        'suspended_at',
        'suspended_reason',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'max_users' => 'integer',
        'max_storage_gb' => 'integer',
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: only currently active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Resolve a tenant by stable code (e.g. 'default', 'rari', 'wdb').
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
