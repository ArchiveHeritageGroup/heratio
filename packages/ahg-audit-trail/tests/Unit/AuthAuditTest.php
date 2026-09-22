<?php

/**
 * AuthAuditTest - sign-in auditing and its tamper-evident copy.
 *
 * A failed POST /login used to be recorded as 'login', indistinguishable from a
 * successful one. Sign-ins and changes are now also appended to the chained
 * ahg_audit_log, with the IP anonymised as the operator's setting requires.
 * Everything runs inside a transaction that is rolled back.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace Tests\Unit;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        foreach (['audit_enabled' => '1', 'audit_authentication' => '1', 'audit_ip_anonymize' => '1'] as $key => $value) {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'setting_group' => 'audit']
            );
        }
        AhgSettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function failLogin(): void
    {
        $this->assertGuest();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.57'])
            ->post('/login', ['email' => 'nobody-audit-test@example.invalid', 'password' => 'wrong-password']);
        $this->assertGuest();
    }

    public function test_failed_login_is_recorded_as_login_failed_not_login(): void
    {
        $before = (int) DB::table('security_audit_log')->max('id');
        $this->failLogin();

        $row = DB::table('security_audit_log')->where('id', '>', $before)->where('action_category', 'auth')->first();

        $this->assertNotNull($row, 'no auth row written for the POST /login');
        $this->assertSame('login_failed', $row->action);
        $this->assertSame('203.0.113.0', $row->ip_address);
        $this->assertStringNotContainsString('wrong-password', (string) $row->details);
    }

    public function test_sign_in_attempt_is_also_appended_to_the_chained_log_with_the_ip_anonymised(): void
    {
        $before = (int) DB::table('ahg_audit_log')->max('id');
        $this->failLogin();

        $row = DB::table('ahg_audit_log')->where('id', '>', $before)->where('action', 'login_failed')->first();

        $this->assertNotNull($row, 'sign-in attempt was not appended to ahg_audit_log');
        $this->assertSame('203.0.113.0', $row->ip_address);
        $meta = json_decode((string) $row->metadata, true);
        $this->assertSame('auth', $meta['category'] ?? null);
        $this->assertArrayHasKey('security_audit_log_id', $meta);
    }
}
