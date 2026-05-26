<?php

/**
 * AuditLogger — write rows to ahg_audit_log with proper old/new value capture.
 *
 * Phase 4 of issue #676 — the schema has `old_values`, `new_values`,
 * `changed_fields` JSON columns but until now most write sites left them
 * NULL. This helper makes it trivial for callers to populate them.
 *
 *   $logger = new AuditLogger();
 *   $logger->logUpdate(
 *       entityType: 'information_object',
 *       entityId:   $io->id,
 *       oldValues:  $oldRow,            // array snapshot BEFORE the change
 *       newValues:  $newRow,            // array snapshot AFTER the change
 *       metadata:   ['source' => 'edit-form'],
 *   );
 *
 * The helper auto-computes `changed_fields` (the set of keys whose values
 * differ between $oldValues and $newValues), JSON-encodes everything, and
 * inserts a row in ahg_audit_log with full user/IP/UA context.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing
 * AGPL-3.0-or-later
 */

namespace AhgAuditTrail\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Optional explicit tenant override. Set via constructor or with()
     * for callers that need to pin the row to a specific tenant regardless
     * of the request / auth context (e.g. background jobs running on behalf
     * of a tenant they are not currently logged in as).
     */
    private ?int $tenantOverride;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantOverride = $tenantId;
    }

    /**
     * Return a clone of this logger with a pinned tenant_id. Useful when a
     * caller resolves a tenant out-of-band (e.g. from a job payload) and
     * wants every subsequent log call to carry it.
     */
    public function withTenant(?int $tenantId): self
    {
        $clone = clone $this;
        $clone->tenantOverride = $tenantId;
        return $clone;
    }

    public function logCreate(
        string $entityType,
        ?int $entityId = null,
        array $newValues = [],
        array $metadata = [],
        ?string $entitySlug = null,
        ?string $entityTitle = null,
    ): ?int {
        return $this->insert([
            'action' => 'create',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug,
            'entity_title' => $entityTitle,
            'old_values' => null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'changed_fields' => $newValues ? json_encode(array_keys($newValues)) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]);
    }

    public function logUpdate(
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        array $metadata = [],
        ?string $entitySlug = null,
        ?string $entityTitle = null,
    ): ?int {
        // Compute changed_fields = keys where value differs. Honours scalar
        // comparison + nested arrays (json_encode compare). Skips keys that
        // are identical in both — keeps the diff payload small.
        $changed = $this->changedKeys($oldValues, $newValues);
        if (empty($changed) && empty($metadata)) {
            // Nothing actually changed — caller probably hit submit without
            // edits. Skip the audit row to avoid noise.
            return null;
        }
        // Restrict payloads to ONLY the changed fields — keeps the audit
        // table compact + makes diffs easy to read.
        $oldDiff = array_intersect_key($oldValues, array_flip($changed));
        $newDiff = array_intersect_key($newValues, array_flip($changed));

        return $this->insert([
            'action' => 'update',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug,
            'entity_title' => $entityTitle,
            'old_values' => $oldDiff ? json_encode($oldDiff, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newDiff ? json_encode($newDiff, JSON_UNESCAPED_UNICODE) : null,
            'changed_fields' => $changed ? json_encode($changed) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]);
    }

    public function logDelete(
        string $entityType,
        ?int $entityId,
        array $oldValues = [],
        array $metadata = [],
        ?string $entitySlug = null,
        ?string $entityTitle = null,
    ): ?int {
        return $this->insert([
            'action' => 'delete',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug,
            'entity_title' => $entityTitle,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => null,
            'changed_fields' => $oldValues ? json_encode(array_keys($oldValues)) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]);
    }

    /**
     * Generic audit row for actions that don't fit create/update/delete
     * (e.g. publish, unpublish, approve, reject, login, download).
     */
    public function logAction(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $entitySlug = null,
        ?string $entityTitle = null,
    ): ?int {
        return $this->insert([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug,
            'entity_title' => $entityTitle,
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => null,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]);
    }

    /**
     * Recompute changed_fields for a row that already exists. Useful when
     * the caller computed old/new but didn't compute the diff. Idempotent.
     */
    public function backfillChangedFields(int $auditRowId): bool
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return false;
        }
        $row = DB::table('ahg_audit_log')->where('id', $auditRowId)
            ->select('old_values', 'new_values', 'changed_fields')->first();
        if (! $row) {
            return false;
        }
        $old = $row->old_values ? (json_decode($row->old_values, true) ?: []) : [];
        $new = $row->new_values ? (json_decode($row->new_values, true) ?: []) : [];
        $changed = $this->changedKeys($old, $new);
        DB::table('ahg_audit_log')->where('id', $auditRowId)
            ->update(['changed_fields' => $changed ? json_encode($changed) : null]);

        return true;
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    /**
     * Compare two associative arrays and return the keys whose values
     * differ. Nested arrays are compared by JSON encoding so the same
     * data in a different order is treated as DIFFERENT (that's the
     * correct behaviour for audit purposes — re-ordering may itself be
     * meaningful).
     */
    public function changedKeys(array $old, array $new): array
    {
        $changed = [];
        foreach (array_keys($old + $new) as $k) {
            $a = $old[$k] ?? null;
            $b = $new[$k] ?? null;
            if (is_array($a) || is_array($b)) {
                if (json_encode($a) !== json_encode($b)) {
                    $changed[] = $k;
                }
            } elseif ($a !== $b) {
                $changed[] = $k;
            }
        }

        return $changed;
    }

    private function insert(array $cols): ?int
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return null;
        }
        try {
            // Resolve user context from auth() (Laravel) — fall back to
            // session if auth() isn't available (CLI / queue worker).
            $userId = null;
            $username = null;
            $userEmail = null;
            try {
                if (auth()->check()) {
                    $u = auth()->user();
                    $userId = $u->id ?? null;
                    $username = $u->username ?? $u->email ?? null;
                    $userEmail = $u->email ?? null;
                }
            } catch (\Throwable $e) {
                // No auth context (CLI / queue) — leave nulls
            }
            $ip = null;
            $ua = null;
            $reqMethod = null;
            $reqUri = null;
            $sessionId = null;
            try {
                $req = request();
                if ($req) {
                    $ip = $req->ip();
                    $ua = substr((string) $req->userAgent(), 0, 500);
                    $reqMethod = $req->method();
                    $reqUri = substr((string) $req->fullUrl(), 0, 2000);
                    $sessionId = method_exists($req, 'session') && $req->hasSession()
                        ? $req->session()->getId() : null;
                }
            } catch (\Throwable $e) {
                // No request context
            }

            // Issue #676 Phase 6 - resolve tenant_id from (in order):
            //   1. explicit constructor / withTenant() override
            //   2. config('ahg.tenant_id') / env('AHG_TENANT_ID')
            //   3. Auth::user()->tenant_id when authenticated
            //   4. NULL (single-tenant / unknown)
            $tenantId = $this->resolveTenantId($userId !== null ? auth()->user() : null);

            $row = array_merge([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'username' => $username,
                'user_email' => $userEmail,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'session_id' => $sessionId,
                'request_method' => $reqMethod,
                'request_uri' => $reqUri,
                'module' => null,
                'action_name' => null,
                'status' => 'success',
                'created_at' => now(),
            ], $cols);

            // Drop the tenant_id key if the schema does not have it yet (fresh
            // install before the install-tenant.sql probe has run). This keeps
            // the writer safe across upgrade boundaries.
            if (!Schema::hasColumn('ahg_audit_log', 'tenant_id')) {
                unset($row['tenant_id']);
            }

            // Issue #676 Phase 5 - route through ChainedAuditWriter so each
            // new row joins the Ed25519/JCS hash chain. Falls back to a
            // plain unsigned insert if the signing key is unavailable so the
            // historical "never break the call path" contract still holds.
            $writer = $this->resolveWriter();
            return $writer->append($row);
        } catch (\Throwable $e) {
            // Never let audit break the calling code path
            return null;
        }
    }

    /**
     * Resolve the tenant_id following the documented precedence:
     *   1. explicit constructor / withTenant() override
     *   2. config('ahg.tenant_id') (env-backed via AHG_TENANT_ID)
     *   3. authenticated user's tenant_id column
     *   4. null
     */
    private function resolveTenantId($authUser): ?int
    {
        if ($this->tenantOverride !== null) {
            return $this->tenantOverride;
        }
        try {
            if (function_exists('config')) {
                $cfg = config('ahg.tenant_id');
                if ($cfg !== null && $cfg !== '') {
                    return (int) $cfg;
                }
            }
        } catch (\Throwable $e) {
            // config() unreachable - fall through
        }
        try {
            if (function_exists('env')) {
                $envVal = env('AHG_TENANT_ID');
                if ($envVal !== null && $envVal !== '') {
                    return (int) $envVal;
                }
            }
        } catch (\Throwable $e) {
            // env() unreachable - fall through
        }
        if ($authUser !== null) {
            $t = $authUser->tenant_id ?? null;
            if ($t !== null && $t !== '') {
                return (int) $t;
            }
        }
        return null;
    }

    /**
     * Resolve the chain writer via the service container when possible so the
     * singleton picks up the shared key resolution + warn-once state, but
     * happily fall back to a fresh instance for contexts (early-boot, tests,
     * CLI install scripts) where the container is not yet wired up.
     */
    private function resolveWriter(): ChainedAuditWriter
    {
        try {
            if (function_exists('app')) {
                $app = app();
                if ($app !== null && $app->bound(ChainedAuditWriter::class)) {
                    return $app->make(ChainedAuditWriter::class);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return new ChainedAuditWriter();
    }
}
