<?php

/**
 * TenantFileService - tenant-scoped wrappers around the central
 * config/heratio.php storage paths.
 *
 * Phase 1 of issue #651. Pure path helpers - this class does not touch
 * the filesystem. Callers use Laravel's Storage::disk() (or
 * file_*() / fopen) against the returned absolute paths.
 *
 * Behaviour:
 *   - When a tenant context exists, returns {base_path}/tenant-{id}/
 *   - When no tenant context exists, returns the unscoped base path so
 *     existing single-tenant deployments keep behaving identically.
 *
 * Used by file-handling services (digital_object writers, backup jobs,
 * scan ingest, OAIS package assembly) once they migrate in Phase 2.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Services;

class TenantFileService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    /**
     * Uploads root, optionally scoped to a tenant. Pass null (or omit) to
     * derive the tenant from TenantContext::current(); pass 0 to force the
     * unscoped path; pass any positive int to pin a specific tenant.
     */
    public function uploadsPath(?int $tenantId = null): string
    {
        return $this->scoped($this->base('uploads_path'), $tenantId);
    }

    /**
     * Backups root, optionally scoped to a tenant.
     */
    public function backupsPath(?int $tenantId = null): string
    {
        return $this->scoped($this->base('backups_path'), $tenantId);
    }

    /**
     * Generic storage_path scoping. Most callers want uploadsPath() /
     * backupsPath(); this is for code that needs an arbitrary key out of
     * config/heratio.php (e.g. config('heratio.packages_path')).
     */
    public function storagePath(?int $tenantId = null): string
    {
        return $this->scoped($this->base('storage_path'), $tenantId);
    }

    /**
     * The path segment a tenant's files live under. Exposed so callers
     * with their own base directory (e.g. ahg-scan staging) can compose
     * the suffix without duplicating the convention.
     *
     * Returns '' when there is no tenant context (single-tenant fallback).
     */
    public function segmentFor(?int $tenantId = null): string
    {
        $resolved = $this->resolveTenantId($tenantId);
        return $resolved === null ? '' : 'tenant-'.$resolved;
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    private function base(string $key): string
    {
        $val = config('heratio.'.$key);
        if (! is_string($val) || $val === '') {
            // Should never happen on a properly bootstrapped install, but
            // fall back to base_path('uploads') the same way heratio.php
            // does so this can not blow up early-boot smoke checks.
            $val = function_exists('base_path') ? base_path('uploads') : 'uploads';
        }
        return rtrim($val, '/');
    }

    private function scoped(string $base, ?int $tenantId): string
    {
        $resolved = $this->resolveTenantId($tenantId);
        if ($resolved === null) {
            return $base;
        }
        return $base.'/tenant-'.$resolved;
    }

    /**
     * Resolve a tenant id from explicit arg, then current context.
     *
     *   null  - derive from TenantContext::current()
     *   0     - explicit "force unscoped"
     *   >0    - pin to this tenant
     *   <0    - treated as unscoped (defensive)
     */
    private function resolveTenantId(?int $tenantId): ?int
    {
        if ($tenantId === 0 || ($tenantId !== null && $tenantId < 0)) {
            return null;
        }
        if ($tenantId !== null) {
            return $tenantId;
        }
        return $this->context->currentId();
    }
}
