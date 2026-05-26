<?php

/**
 * MfaPolicyService - per-tenant MFA enforcement policy resolution (issue #723).
 *
 * Layers a tenant admin policy on top of the opt-in TOTP / WebAuthn / OTP
 * factors shipped in #690 / #721 / #722. The policy answers three questions:
 *
 *   - policyFor($tenantId)  -> resolve the effective policy row, falling
 *                              back to the global default when the tenant
 *                              has none, and to a hardcoded "optional / 7d"
 *                              row when no policy table data exists at all.
 *   - requiresMfa($user)    -> true when the resolved policy says the user
 *                              must enrol (taking role scoping into account).
 *   - inGrace($user)        -> true when the user is inside the grace window
 *                              and may defer enrolment (banner, not block).
 *
 * The enforcement vocabulary lives in ahg_dropdown taxonomy 'mfa_enforcement':
 *
 *   off                    factor enrolment hidden; nothing required
 *   optional               default; user choice
 *   required_for_admins    admin + editor groups must enrol
 *   required               every authenticated user must enrol
 *
 * Resolution order for the effective policy row:
 *
 *   1. ahg_mfa_policy WHERE tenant_id = $tenantId
 *   2. ahg_mfa_policy WHERE tenant_id IS NULL  (global default)
 *   3. synthetic ('optional', 7)               hardcoded fallback
 *
 * The grace window is computed against MAX(policy.updated_at, user.created_at)
 * so a tenant flipping policy from 'optional' to 'required' starts the clock
 * fresh - existing users get the grace window from the flip, not from when
 * the account was created.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

use AhgCore\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MfaPolicyService
{
    public const ENFORCEMENT_OFF = 'off';
    public const ENFORCEMENT_OPTIONAL = 'optional';
    public const ENFORCEMENT_REQUIRED_FOR_ADMINS = 'required_for_admins';
    public const ENFORCEMENT_REQUIRED = 'required';

    public const VALID_ENFORCEMENTS = [
        self::ENFORCEMENT_OFF,
        self::ENFORCEMENT_OPTIONAL,
        self::ENFORCEMENT_REQUIRED_FOR_ADMINS,
        self::ENFORCEMENT_REQUIRED,
    ];

    /**
     * Resolve the effective policy for a tenant.
     *
     * Returns a plain stdClass with these fields:
     *   - id                   (int|null - null for the synthetic fallback)
     *   - tenant_id            (int|null)
     *   - enforcement          (string)
     *   - grace_period_days    (int)
     *   - updated_at           (string|null ISO timestamp, null for synthetic)
     *   - is_global_default    (bool)  true when the global row was used
     *   - is_synthetic         (bool)  true when nothing was found in DB
     */
    public function policyFor(?int $tenantId): object
    {
        // Defensive: in CI / fresh installs the table may not exist yet.
        if (! Schema::hasTable('ahg_mfa_policy')) {
            return $this->syntheticFallback();
        }

        if ($tenantId !== null) {
            $row = DB::table('ahg_mfa_policy')->where('tenant_id', $tenantId)->first();
            if ($row) {
                return $this->normalise($row, isGlobalDefault: false, isSynthetic: false);
            }
        }

        $global = DB::table('ahg_mfa_policy')->whereNull('tenant_id')->first();
        if ($global) {
            return $this->normalise($global, isGlobalDefault: true, isSynthetic: false);
        }

        return $this->syntheticFallback();
    }

    /**
     * Does this user have to enrol an MFA factor?
     *
     * Returns false when:
     *   - enforcement is 'off' or 'optional'
     *   - enforcement is 'required_for_admins' AND user is not admin/editor
     *   - the user already has an MFA factor enrolled (TOTP, WebAuthn, OTP)
     *
     * Returns true when the policy demands enrolment and the user has no
     * verified factor yet. The caller (EnforceMfaPolicy middleware) then
     * decides whether to redirect or just flash a banner based on inGrace().
     */
    public function requiresMfa(User $user): bool
    {
        $tenantId = $this->resolveUserTenantId($user);
        $policy = $this->policyFor($tenantId);

        if ($policy->enforcement === self::ENFORCEMENT_OFF
            || $policy->enforcement === self::ENFORCEMENT_OPTIONAL) {
            return false;
        }

        if ($policy->enforcement === self::ENFORCEMENT_REQUIRED_FOR_ADMINS) {
            if (! $this->userIsAdminOrEditor($user)) {
                return false;
            }
        }

        return ! $this->userHasEnrolledFactor((int) $user->id);
    }

    /**
     * Is the user still inside the grace window?
     *
     * Returns true when the policy says MFA is required but the user
     * hasn't run out the clock yet - the EnforceMfaPolicy middleware
     * surfaces a yellow banner via a flash message in this case rather
     * than redirecting to /security-clearance/setup-2fa.
     *
     * Grace is computed from the most recent of:
     *   - policy.updated_at  (so a fresh "required" flip resets the clock)
     *   - user.created_at    (so brand-new accounts get the full window)
     *
     * Defensive defaults: when the user has no created_at timestamp or the
     * policy is synthetic, fall back to "now" so the user gets the full
     * window from this very request.
     */
    public function inGrace(User $user): bool
    {
        $tenantId = $this->resolveUserTenantId($user);
        $policy = $this->policyFor($tenantId);

        $graceDays = (int) $policy->grace_period_days;
        if ($graceDays <= 0) {
            return false;
        }

        $anchor = $this->graceAnchor($user, $policy);
        if ($anchor === null) {
            return true; // No anchor -> generous default, full window from now.
        }

        $expires = $anchor + ($graceDays * 86400);

        return time() < $expires;
    }

    /**
     * List every tenant + its current policy (tenant-specific row OR the
     * global default, marked accordingly). Used by the admin UI listing.
     */
    public function listAllForAdmin(): array
    {
        if (! Schema::hasTable('ahg_mfa_policy')) {
            return [];
        }

        $tenants = Schema::hasTable('ahg_tenant')
            ? DB::table('ahg_tenant')->select('id', 'code', 'name')->orderBy('name')->get()
            : collect();

        $rows = [];
        foreach ($tenants as $tenant) {
            $policy = $this->policyFor((int) $tenant->id);
            $rows[] = (object) [
                'tenant_id' => (int) $tenant->id,
                'tenant_code' => $tenant->code,
                'tenant_name' => $tenant->name,
                'enforcement' => $policy->enforcement,
                'grace_period_days' => $policy->grace_period_days,
                'is_global_default' => $policy->is_global_default,
                'is_synthetic' => $policy->is_synthetic,
            ];
        }

        return $rows;
    }

    /**
     * Upsert the policy for one tenant. Pass null to write the global
     * default row. Validates enforcement against VALID_ENFORCEMENTS and
     * clamps grace_period_days to 0..365.
     */
    public function setPolicy(?int $tenantId, string $enforcement, int $gracePeriodDays): void
    {
        if (! in_array($enforcement, self::VALID_ENFORCEMENTS, true)) {
            throw new \InvalidArgumentException("Invalid enforcement: {$enforcement}");
        }
        $gracePeriodDays = max(0, min(365, $gracePeriodDays));

        $existing = DB::table('ahg_mfa_policy')
            ->when($tenantId === null,
                fn ($q) => $q->whereNull('tenant_id'),
                fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $payload = [
            'tenant_id' => $tenantId,
            'enforcement' => $enforcement,
            'grace_period_days' => $gracePeriodDays,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('ahg_mfa_policy')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        $payload['created_at'] = now();
        DB::table('ahg_mfa_policy')->insert($payload);
    }

    /**
     * Delete a tenant-specific row so the tenant falls back to the global
     * default. No-op when the tenant has no specific row. Refuses to delete
     * the global default row (tenant_id IS NULL) so the fallback is always
     * available.
     */
    public function resetToGlobalDefault(int $tenantId): void
    {
        DB::table('ahg_mfa_policy')->where('tenant_id', $tenantId)->delete();
    }

    /**
     * Read the user's current tenant. Heratio's authoritative source is
     * the ahg_tenant_user pivot - we pick the primary row when present,
     * else fall back to TenantContext::currentId(), else null (global).
     *
     * Wrapped in a try/catch because tests + fresh installs may not have
     * the multi-tenant package loaded.
     */
    private function resolveUserTenantId(User $user): ?int
    {
        if (Schema::hasTable('ahg_tenant_user')) {
            $primary = DB::table('ahg_tenant_user')
                ->where('user_id', $user->id)
                ->where('is_primary', 1)
                ->value('tenant_id');
            if ($primary !== null) {
                return (int) $primary;
            }
            $any = DB::table('ahg_tenant_user')
                ->where('user_id', $user->id)
                ->orderBy('assigned_at', 'asc')
                ->value('tenant_id');
            if ($any !== null) {
                return (int) $any;
            }
        }

        try {
            if (class_exists(\AhgMultiTenant\Facades\TenantContext::class)) {
                return \AhgMultiTenant\Facades\TenantContext::currentId();
            }
        } catch (\Throwable $e) {
            // Single-tenant install - fall through.
        }

        return null;
    }

    private function userIsAdminOrEditor(User $user): bool
    {
        try {
            return $user->isAdministrator() || $user->isEditor();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Look across the three MFA factor surfaces for any verified enrolment.
     * Returns true on the FIRST hit so the cost is one indexed read in the
     * common case (a user with TOTP enrolled).
     */
    private function userHasEnrolledFactor(int $userId): bool
    {
        if (Schema::hasTable('user_totp_secret')) {
            $totp = DB::table('user_totp_secret')
                ->where('user_id', $userId)
                ->whereNotNull('enabled_at')
                ->exists();
            if ($totp) {
                return true;
            }
        }

        if (Schema::hasTable('ahg_webauthn_credential')) {
            $webauthn = DB::table('ahg_webauthn_credential')
                ->where('user_id', $userId)
                ->exists();
            if ($webauthn) {
                return true;
            }
        }

        if (Schema::hasTable('ahg_otp_factor')) {
            $otp = DB::table('ahg_otp_factor')
                ->where('user_id', $userId)
                ->whereNotNull('verified_at')
                ->exists();
            if ($otp) {
                return true;
            }
        }

        return false;
    }

    private function graceAnchor(User $user, object $policy): ?int
    {
        $policyTs = $policy->updated_at ? strtotime((string) $policy->updated_at) : null;
        $userTs = isset($user->created_at) && $user->created_at
            ? strtotime((string) $user->created_at) : null;

        if ($policyTs === null && $userTs === null) {
            return null;
        }
        if ($policyTs === null) {
            return $userTs;
        }
        if ($userTs === null) {
            return $policyTs;
        }

        return max($policyTs, $userTs);
    }

    private function normalise(object $row, bool $isGlobalDefault, bool $isSynthetic): object
    {
        return (object) [
            'id' => isset($row->id) ? (int) $row->id : null,
            'tenant_id' => isset($row->tenant_id) ? (int) $row->tenant_id : null,
            'enforcement' => (string) $row->enforcement,
            'grace_period_days' => (int) $row->grace_period_days,
            'updated_at' => $row->updated_at ?? null,
            'is_global_default' => $isGlobalDefault,
            'is_synthetic' => $isSynthetic,
        ];
    }

    private function syntheticFallback(): object
    {
        return (object) [
            'id' => null,
            'tenant_id' => null,
            'enforcement' => self::ENFORCEMENT_OPTIONAL,
            'grace_period_days' => 7,
            'updated_at' => null,
            'is_global_default' => true,
            'is_synthetic' => true,
        ];
    }
}
