<?php

/**
 * TenantScope - apply the active tenant's repository filter to a query.
 *
 * The tenant is app('tenant.current') when ResolveTenantMiddleware
 * (ahg-multi-tenant) bound one for a web request; otherwise (the API, which
 * does not run the web middleware) it is resolved here from the request host,
 * then the caller's own tenant assignment, then the default tenant. The
 * caller is the signed-in user or the owner of the API key. Reading the
 * tables directly keeps ahg-core a leaf package.
 *
 * A record belongs to the repository of its nearest ancestor-or-self that has
 * one, as in AtoM: most descendants carry no repository_id of their own.
 *
 * No-op when:
 *   - multi-tenancy or ahg_settings.tenant_enforce_filter is off;
 *   - there is no request (artisan, queue workers);
 *   - the caller is a Heratio administrator (cross-tenant support visibility);
 *   - the tenant has no repository_id binding (open scope).
 *
 * GHSA-fqxh-fv8c-mq23: the admin check read a user.is_admin column that does
 * not exist, so every signed-in user was unscoped; children were matched on
 * their own (empty) repository_id; the API never resolved a tenant at all.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Support;

use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantScope
{
    /** @var array<int, array<int, array{int,int}>> lft ranges per repository, for this request. */
    private static array $ranges = [];

    /** @var array<int, int[]> Out-of-tenant record ids per repository, for this request. */
    private static array $outside = [];

    /**
     * Limit a query to the active tenant's records. `$repoIdColumn` is
     * "<alias>.repository_id" on an information_object alias, matched through
     * the record's inherited repository; a bare column is compared as is.
     */
    public static function apply($query, string $repoIdColumn = 'repository_id'): void
    {
        $repoId = self::getActiveRepoId();
        if ($repoId === null) {
            return;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.repository_id$/', $repoIdColumn, $m)) {
            self::whereInTenant($query, $m[1].'.lft', $repoId);

            return;
        }
        $query->where($repoIdColumn, $repoId);
    }

    /**
     * Record ids outside the active tenant (empty when no scoping applies).
     * DisclosureGate adds these to its restricted set, so every surface that
     * goes through the gate is tenant-scoped.
     *
     * @return int[]
     */
    public static function outsideIds(): array
    {
        $repoId = self::getActiveRepoId();
        if ($repoId === null) {
            return [];
        }
        if (! isset(self::$outside[$repoId])) {
            // ponytail: a full id list, cached a minute; fine to a few hundred
            // thousand records. Past that, gate callers should take the lft
            // range condition (apply) instead of whereNotIn on this list.
            self::$outside[$repoId] = Cache::remember('tenant_scope_outside_'.$repoId, 60, function () use ($repoId) {
                $q = DB::table('information_object')->where('id', '<>', 1);
                $q->whereNot(fn ($w) => self::whereInTenant($w, 'lft', $repoId));

                return $q->pluck('id')->map('intval')->all();
            });
        }

        return self::$outside[$repoId];
    }

    /**
     * The active tenant's repository_id, or null when scoping should NOT be
     * applied (see the class comment for when).
     */
    public static function getActiveRepoId(): ?int
    {
        try {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return null;
            }
            if (! self::setting('tenant_enabled') || ! self::setting('tenant_enforce_filter')) {
                return null;
            }

            $userId = self::callerId();
            if ($userId > 0 && AclService::isAdministrator((object) ['id' => $userId])) {
                return null;
            }

            $tenant = self::resolveTenant($userId);
            if (! $tenant) {
                return -1; // fail closed: enforcement on, no tenant - no records
            }
            $repoId = (int) ($tenant->repository_id ?? 0);

            return $repoId > 0 ? $repoId : null;
        } catch (\Throwable $e) {
            report($e);

            return -1; // fail closed
        }
    }

    /** Restrict `$lftColumn` to the lft ranges whose inherited repository is `$repoId`. */
    private static function whereInTenant($query, string $lftColumn, int $repoId): void
    {
        $ranges = self::ranges($repoId);
        if (empty($ranges)) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function ($w) use ($ranges, $lftColumn) {
            foreach ($ranges as [$from, $to]) {
                $w->orWhereBetween($lftColumn, [$from, $to]);
            }
        });
    }

    /**
     * The lft ranges where the nearest ancestor-or-self with a repository is
     * `$repoId`, merged. Built from the records that carry a repository_id
     * (usually only top-level descriptions), so there are few ranges.
     *
     * @return array<int, array{int,int}>
     */
    private static function ranges(int $repoId): array
    {
        if (isset(self::$ranges[$repoId])) {
            return self::$ranges[$repoId];
        }
        $anchors = DB::table('information_object')
            ->whereNotNull('repository_id')->where('id', '<>', 1)
            ->orderBy('lft')->get(['lft', 'rgt', 'repository_id']);

        $out = [];
        $emit = function (int $from, int $to, ?int $repo) use (&$out, $repoId) {
            if ($repo !== $repoId || $to < $from) {
                return;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last][1] + 1 >= $from) {
                $out[$last][1] = max($out[$last][1], $to);
            } else {
                $out[] = [$from, $to];
            }
        };
        $stack = [];
        $pos = 0;
        foreach ($anchors as $a) {
            while ($stack && end($stack)->rgt < $a->lft) {
                $top = array_pop($stack);
                $emit($pos, (int) $top->rgt, (int) $top->repository_id);
                $pos = (int) $top->rgt + 1;
            }
            $emit($pos, (int) $a->lft - 1, $stack ? (int) end($stack)->repository_id : null);
            $pos = (int) $a->lft;
            $stack[] = $a;
        }
        while ($stack) {
            $top = array_pop($stack);
            $emit($pos, (int) $top->rgt, (int) $top->repository_id);
            $pos = (int) $top->rgt + 1;
        }

        return self::$ranges[$repoId] = $out;
    }

    /** The signed-in user, or the owner of the API key on this request. */
    private static function callerId(): int
    {
        $id = (int) (auth()->id() ?? 0);
        if ($id === 0 && app()->bound('request')) {
            $id = (int) (request()->attributes->get('api_user_id') ?? 0);
        }

        return $id;
    }

    /**
     * The tenant to scope to. A signed-in caller or key owner who is not a
     * member of the host's (or session's) tenant gets their own tenant instead,
     * so another tenant's address does not open its records to them.
     */
    private static function resolveTenant(int $userId): ?object
    {
        $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;
        if (! $tenant && app()->bound('request')) {
            $host = strtolower(request()->getHost());
            $tenant = DB::table('ahg_tenant')->where('is_active', 1)
                ->where(fn ($q) => $q->where('domain', $host)->orWhere('subdomain', explode('.', $host)[0]))
                ->first();
        }
        if ($userId === 0) {
            return $tenant ?: self::defaultTenant();
        }

        $mine = DB::table('ahg_tenant_user as tu')
            ->join('ahg_tenant as t', 't.id', '=', 'tu.tenant_id')
            ->where('tu.user_id', $userId)->where('t.is_active', 1)
            ->when(Schema::hasColumn('ahg_tenant_user', 'is_primary'), fn ($q) => $q->orderByDesc('tu.is_primary'))
            ->orderBy('t.id')
            ->select('t.*')->get();
        if ($tenant && $mine->contains('id', $tenant->id)) {
            return $tenant;
        }

        return $mine->first() ?: self::defaultTenant();
    }

    private static function defaultTenant(): ?object
    {
        return DB::table('ahg_tenant')->where('is_active', 1)
            ->orderByDesc('is_default')->orderBy('id')->first();
    }

    private static function setting(string $key): bool
    {
        $v = AhgSettingsService::get($key, 'false');

        return $v === 'true' || $v === '1' || $v === 1 || $v === true;
    }

    /** Forget the per-request caches (tests, long-running workers). */
    public static function flush(): void
    {
        self::$ranges = [];
        self::$outside = [];
    }
}
