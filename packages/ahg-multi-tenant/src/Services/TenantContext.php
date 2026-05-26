<?php

/**
 * TenantContext - resolve + scope the active tenant for the current
 * request / job / scope() closure.
 *
 * Phase 1 of issue #651. The framework other phases hang off:
 *
 *   - Phase 2 will add tenant_id columns to digital_object / queue / cache.
 *   - Phase 3+ adds the TenantBoundary middleware that hard-fails any API
 *     request whose target row's tenant_id does not match current().
 *
 * Resolution precedence (highest first):
 *   1. scope($tenantId, $fn) stack override
 *   2. config('ahg.tenant_id') / env AHG_TENANT_ID
 *   3. session('current_tenant_id') from the navbar switcher
 *   4. Auth::user()->tenant_id when authenticated
 *   5. X-Tenant-Id request header (only when tenant_resolution_strategy='header'
 *      AND a valid X-Tenant-Key / Bearer is present)
 *   6. request host (subdomain / domain) per tenant_resolution_strategy
 *   7. path prefix when strategy='path'
 *   8. is_default tenant when one exists
 *   9. first active tenant by id (last-ditch single-tenant fallback)
 *   10. null
 *
 * Bound as the laravel singleton `tenant.context` (also tenant.context::class).
 * Reach it via the facade AhgMultiTenant\Facades\TenantContext.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Services;

use AhgMultiTenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantContext
{
    /**
     * Stack of explicit tenant overrides set via scope(). The TOP of the
     * stack wins. Push on scope() entry, pop on scope() return / throw.
     *
     * @var array<int, int>
     */
    private array $scopeStack = [];

    /**
     * Per-request memo of the resolved tenant so we do not hit the DB on
     * every current() call. Invalidated automatically when scope() mutates
     * the stack.
     */
    private ?Tenant $memo = null;

    /**
     * Sentinel toggle so a single negative resolution sticks for the
     * lifetime of the request. Prevents log floods on installs that
     * legitimately have no tenant rows.
     */
    private bool $resolvedOnce = false;

    /**
     * Return the active tenant for the current request / job, or null when
     * no tenant can be resolved (the single-tenant fallback path).
     */
    public function current(): ?Tenant
    {
        // 1. scope() stack always wins.
        if (! empty($this->scopeStack)) {
            $top = (int) end($this->scopeStack);
            // Resolve from cache only when the memo agrees with the top frame.
            if ($this->memo !== null && (int) $this->memo->id === $top) {
                return $this->memo;
            }
            $tenant = $this->loadTenant($top);
            $this->memo = $tenant;
            return $tenant;
        }

        if ($this->resolvedOnce && $this->memo !== null) {
            return $this->memo;
        }

        $tenant = $this->resolve();
        $this->memo = $tenant;
        $this->resolvedOnce = true;
        return $tenant;
    }

    /**
     * Sugar: return the active tenant id, or null.
     */
    public function currentId(): ?int
    {
        $t = $this->current();
        return $t ? (int) $t->id : null;
    }

    /**
     * Temporarily set the current tenant for the duration of $fn.
     *
     * Restores the previous tenant on return AND on throw (try/finally).
     * Nested scope() calls compose naturally - each pushes a new frame
     * on the stack and pops it on exit.
     *
     * Returns whatever $fn returns.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function scope(int $tenantId, callable $fn): mixed
    {
        $this->scopeStack[] = $tenantId;
        $previousMemo = $this->memo;
        $this->memo = null; // force re-resolve under the new frame
        try {
            return $fn();
        } finally {
            array_pop($this->scopeStack);
            // Bust the memo so the next current() call re-resolves against
            // whatever frame is now on top (or the natural resolution chain
            // when the stack is empty again).
            $this->memo = empty($this->scopeStack) ? $previousMemo : null;
            $this->resolvedOnce = empty($this->scopeStack) ? $this->resolvedOnce : false;
        }
    }

    /**
     * Clear all cached state. Mostly useful for tests that swap out the
     * request mid-flight - prod code rarely needs this.
     */
    public function forget(): void
    {
        $this->memo = null;
        $this->resolvedOnce = false;
    }

    /**
     * Depth of the override stack (test helper).
     */
    public function scopeDepth(): int
    {
        return count($this->scopeStack);
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    private function resolve(): ?Tenant
    {
        // Defensive: a fresh install before the install.sql runs won't
        // have ahg_tenant yet. Bail safely.
        try {
            if (! Schema::hasTable('ahg_tenant')) {
                return null;
            }
        } catch (Throwable $e) {
            return null;
        }

        // 2. static override (env / config)
        $static = $this->staticOverride();
        if ($static !== null) {
            $tenant = $this->loadTenant($static);
            if ($tenant) {
                return $tenant;
            }
        }

        // 3. session
        try {
            if (function_exists('session') && session()->isStarted()) {
                $sid = (int) session('current_tenant_id', 0);
                if ($sid > 0) {
                    $tenant = $this->loadTenant($sid);
                    if ($tenant && $tenant->is_active) {
                        return $tenant;
                    }
                }
            }
        } catch (Throwable $e) {
            // session unavailable (CLI / queue) - fall through
        }

        // 4. auth()->user()->tenant_id
        try {
            if (function_exists('auth') && auth()->check()) {
                $user = auth()->user();
                $uid = $user->tenant_id ?? null;
                if ($uid !== null && $uid !== '') {
                    $tenant = $this->loadTenant((int) $uid);
                    if ($tenant && $tenant->is_active) {
                        return $tenant;
                    }
                }
            }
        } catch (Throwable $e) {
            // no auth context
        }

        $strategy = $this->strategy();

        // 5. X-Tenant-Id header (requires matching key)
        if ($strategy === 'header') {
            $tenant = $this->resolveFromHeader();
            if ($tenant) {
                return $tenant;
            }
        }

        // 6. host (domain / subdomain)
        if (in_array($strategy, ['domain', 'subdomain'], true)) {
            $tenant = $this->resolveFromHost($strategy);
            if ($tenant) {
                return $tenant;
            }
        }

        // 7. first path segment matches code
        if ($strategy === 'path') {
            $tenant = $this->resolveFromPath();
            if ($tenant) {
                return $tenant;
            }
        }

        // 8. is_default
        $tenant = Tenant::query()
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        return $tenant;
    }

    private function staticOverride(): ?int
    {
        try {
            if (function_exists('config')) {
                $cfg = config('ahg.tenant_id');
                if ($cfg !== null && $cfg !== '') {
                    return (int) $cfg;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        try {
            if (function_exists('env')) {
                $env = env('AHG_TENANT_ID');
                if ($env !== null && $env !== '') {
                    return (int) $env;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        return null;
    }

    private function strategy(): string
    {
        try {
            return (string) config('ahg.tenant_resolution_strategy', 'domain');
        } catch (Throwable $e) {
            return 'domain';
        }
    }

    private function resolveFromHost(string $strategy): ?Tenant
    {
        try {
            if (! function_exists('request')) {
                return null;
            }
            $req = request();
            if (! $req) {
                return null;
            }
            $host = strtolower((string) $req->getHost());
            if ($host === '') {
                return null;
            }

            if ($strategy === 'domain') {
                $tenant = Tenant::query()
                    ->where('domain', $host)
                    ->where('is_active', 1)
                    ->first();
                if ($tenant) {
                    return $tenant;
                }
            }

            // Both strategies fall back to first-label match against
            // ahg_tenant.subdomain - 'domain' tries it as a courtesy so a
            // typo on a single setting does not silently fail.
            $label = explode('.', $host)[0] ?? null;
            if ($label) {
                return Tenant::query()
                    ->where('subdomain', $label)
                    ->where('is_active', 1)
                    ->first();
            }
        } catch (Throwable $e) {
            // no request context
        }
        return null;
    }

    private function resolveFromHeader(): ?Tenant
    {
        try {
            if (! function_exists('request')) {
                return null;
            }
            $req = request();
            if (! $req) {
                return null;
            }
            $headerId = $req->header('X-Tenant-Id');
            if ($headerId === null || $headerId === '') {
                return null;
            }
            $expected = (string) config('ahg.tenant_api_key', '');
            if ($expected === '') {
                return null; // header-mode requires a configured key
            }
            $supplied = (string) ($req->header('X-Tenant-Key') ?? '');
            if ($supplied === '') {
                $auth = (string) ($req->header('Authorization') ?? '');
                if (stripos($auth, 'Bearer ') === 0) {
                    $supplied = trim(substr($auth, 7));
                }
            }
            if (! hash_equals($expected, $supplied)) {
                return null;
            }
            return $this->loadTenant((int) $headerId);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolveFromPath(): ?Tenant
    {
        try {
            if (! function_exists('request')) {
                return null;
            }
            $req = request();
            if (! $req) {
                return null;
            }
            $path = trim((string) $req->path(), '/');
            $first = explode('/', $path)[0] ?? '';
            if ($first === '') {
                return null;
            }
            return Tenant::query()
                ->where('code', $first)
                ->where('is_active', 1)
                ->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function loadTenant(int $id): ?Tenant
    {
        try {
            return Tenant::query()->where('id', $id)->first();
        } catch (Throwable $e) {
            return null;
        }
    }
}
