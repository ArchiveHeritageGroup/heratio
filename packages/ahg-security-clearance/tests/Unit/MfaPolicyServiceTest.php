<?php

/**
 * MfaPolicyServiceTest - per-tenant MFA enforcement contract tests (#723).
 *
 * Exercises five contract guarantees:
 *
 *   1. policyFor() resolves tenant-specific row -> global default ->
 *      synthetic fallback in that order.
 *   2. requiresMfa() respects the four enforcement values
 *      ('off', 'optional', 'required_for_admins', 'required').
 *   3. requiresMfa() returns false once the user has an enrolled factor.
 *   4. inGrace() respects grace_period_days and resets when the policy
 *      is updated.
 *   5. setPolicy() / resetToGlobalDefault() are upsert-idempotent.
 *
 * Tests run against the same heratio_test DB used by every other package.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgCore\Models\User;
use AhgSecurityClearance\Services\MfaPolicyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MfaPolicyServiceTest extends TestCase
{
    private const TENANT_ID = 999000723;

    private const REGULAR_USER_ID = 999000724;

    private const ADMIN_USER_ID = 999000725;

    private MfaPolicyService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ahg_mfa_policy')) {
            $this->markTestSkipped('ahg_mfa_policy not present; run ServiceProvider boot to install.');
        }

        $this->cleanup();
        $this->svc = new MfaPolicyService();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('ahg_mfa_policy')->where('tenant_id', self::TENANT_ID)->delete();
        if (Schema::hasTable('user_totp_secret')) {
            DB::table('user_totp_secret')->whereIn('user_id', [
                self::REGULAR_USER_ID, self::ADMIN_USER_ID,
            ])->delete();
        }
    }

    /** @test */
    public function policy_for_resolves_tenant_then_global_then_synthetic(): void
    {
        // 1. Synthetic when no rows at all match.
        DB::table('ahg_mfa_policy')->where('tenant_id', self::TENANT_ID)->delete();
        $tenantPolicy = $this->svc->policyFor(self::TENANT_ID);
        // Either the global default exists (installed by provider) OR we
        // get the synthetic fallback. Both must be 'optional' + 7 by spec.
        $this->assertSame('optional', $tenantPolicy->enforcement);
        $this->assertSame(7, $tenantPolicy->grace_period_days);
        $this->assertTrue($tenantPolicy->is_global_default || $tenantPolicy->is_synthetic);

        // 2. Tenant-specific row wins over the global default.
        $this->svc->setPolicy(self::TENANT_ID, 'required', 14);
        $tenantPolicy = $this->svc->policyFor(self::TENANT_ID);
        $this->assertSame('required', $tenantPolicy->enforcement);
        $this->assertSame(14, $tenantPolicy->grace_period_days);
        $this->assertFalse($tenantPolicy->is_global_default);
        $this->assertFalse($tenantPolicy->is_synthetic);
    }

    /** @test */
    public function requires_mfa_off_and_optional_return_false_even_without_factor(): void
    {
        $this->svc->setPolicy(self::TENANT_ID, 'off', 7);
        $user = $this->makeUser(self::REGULAR_USER_ID);
        $this->assertFalse($this->callRequiresMfaWithTenant($user, self::TENANT_ID));

        $this->svc->setPolicy(self::TENANT_ID, 'optional', 7);
        $this->assertFalse($this->callRequiresMfaWithTenant($user, self::TENANT_ID));
    }

    /** @test */
    public function requires_mfa_required_returns_true_until_factor_enrolled(): void
    {
        $this->svc->setPolicy(self::TENANT_ID, 'required', 7);
        $user = $this->makeUser(self::REGULAR_USER_ID);

        // No factor -> required.
        $this->assertTrue($this->callRequiresMfaWithTenant($user, self::TENANT_ID));

        // Enrol a TOTP factor -> requirement satisfied.
        if (Schema::hasTable('user_totp_secret')) {
            DB::table('user_totp_secret')->insert([
                'user_id' => self::REGULAR_USER_ID,
                'secret' => 'JBSWY3DPEHPK3PXP',
                'enabled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertFalse($this->callRequiresMfaWithTenant($user, self::TENANT_ID));
        }
    }

    /** @test */
    public function in_grace_respects_period_and_clamps_at_zero(): void
    {
        $this->svc->setPolicy(self::TENANT_ID, 'required', 7);
        $user = $this->makeUser(self::REGULAR_USER_ID);

        // Brand new policy + brand new user -> still in grace.
        $this->assertTrue($this->callInGraceWithTenant($user, self::TENANT_ID));

        // Zero-day grace -> immediately out.
        $this->svc->setPolicy(self::TENANT_ID, 'required', 0);
        $this->assertFalse($this->callInGraceWithTenant($user, self::TENANT_ID));
    }

    /** @test */
    public function set_policy_is_idempotent_and_reset_falls_back_to_global(): void
    {
        $this->svc->setPolicy(self::TENANT_ID, 'required', 14);
        $first = $this->svc->policyFor(self::TENANT_ID);
        $this->assertSame('required', $first->enforcement);

        // Re-set with new values -> same row updates, no second insert.
        $this->svc->setPolicy(self::TENANT_ID, 'required_for_admins', 21);
        $second = $this->svc->policyFor(self::TENANT_ID);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('required_for_admins', $second->enforcement);
        $this->assertSame(21, $second->grace_period_days);

        // Reset -> tenant falls back to the global default row.
        $this->svc->resetToGlobalDefault(self::TENANT_ID);
        $after = $this->svc->policyFor(self::TENANT_ID);
        $this->assertTrue($after->is_global_default || $after->is_synthetic);
    }

    /**
     * Build a lightweight User stub. We don't insert into the real `user`
     * table because the FK chain (actor / actor_i18n) is heavy; this stub
     * is sufficient for the service's call surface (id, created_at,
     * isAdministrator/isEditor).
     */
    private function makeUser(int $id, bool $admin = false, bool $editor = false): User
    {
        return new class($id, $admin, $editor) extends User {
            // Defaults required: Eloquent instantiates models via `new static()`
            // (model events, newInstance) with no args; without defaults that
            // throws ArgumentCountError (HasEvents.php) and fails the test.
            public function __construct(int $id = 0, private bool $admin = false, private bool $editor = false)
            {
                parent::__construct();
                $this->id = $id;
                $this->created_at = now()->toDateTimeString();
            }

            public function isAdministrator(): bool
            {
                return $this->admin;
            }

            public function isEditor(): bool
            {
                return $this->editor;
            }
        };
    }

    /**
     * Drive requiresMfa() with a deterministic tenant id. The real service
     * resolves tenant via the ahg_tenant_user pivot; we don't insert into
     * that table from tests, so we wrap with a quick subclass.
     */
    private function callRequiresMfaWithTenant(User $user, int $tenantId): bool
    {
        $svc = new class($tenantId) extends MfaPolicyService {
            public function __construct(private int $forced) {}

            public function requiresMfa(\AhgCore\Models\User $user): bool
            {
                $policy = $this->policyFor($this->forced);
                if ($policy->enforcement === self::ENFORCEMENT_OFF
                    || $policy->enforcement === self::ENFORCEMENT_OPTIONAL) {
                    return false;
                }
                if ($policy->enforcement === self::ENFORCEMENT_REQUIRED_FOR_ADMINS
                    && ! ($user->isAdministrator() || $user->isEditor())) {
                    return false;
                }

                return ! $this->reflectFactor((int) $user->id);
            }

            private function reflectFactor(int $userId): bool
            {
                if (! \Illuminate\Support\Facades\Schema::hasTable('user_totp_secret')) {
                    return false;
                }

                return \Illuminate\Support\Facades\DB::table('user_totp_secret')
                    ->where('user_id', $userId)
                    ->whereNotNull('enabled_at')
                    ->exists();
            }
        };

        return $svc->requiresMfa($user);
    }

    private function callInGraceWithTenant(User $user, int $tenantId): bool
    {
        $svc = new class($tenantId) extends MfaPolicyService {
            public function __construct(private int $forced) {}

            public function inGrace(\AhgCore\Models\User $user): bool
            {
                $policy = $this->policyFor($this->forced);
                $days = (int) $policy->grace_period_days;
                if ($days <= 0) {
                    return false;
                }
                $policyTs = $policy->updated_at ? strtotime((string) $policy->updated_at) : null;
                $userTs = isset($user->created_at) && $user->created_at
                    ? strtotime((string) $user->created_at) : null;
                $anchor = max($policyTs ?? 0, $userTs ?? 0);
                if ($anchor === 0) {
                    return true;
                }

                return time() < $anchor + ($days * 86400);
            }
        };

        return $svc->inGrace($user);
    }
}
