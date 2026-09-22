<?php

/**
 * AuditServiceTest - the service other packages call to write audit rows.
 *
 * It declared the wrong namespace, so every caller's class_exists() check was
 * false and none of their events were ever recorded; its settings were also
 * cached for the life of the process. Runs inside a rolled-back transaction.
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

use AhgAuditTrail\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->setting('audit_enabled', '1');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value, 'setting_group' => 'audit']
        );
    }

    public function test_the_class_callers_check_for_actually_exists(): void
    {
        $this->assertTrue(class_exists('\\AhgAuditTrail\\Services\\AuditService'));
    }

    public function test_log_writes_the_row_with_object_type_and_details(): void
    {
        $id = app(AuditService::class)->log('sharepoint.ingest', 42, null, [
            'object_type' => 'informationobject',
            'details' => ['sp_item_id' => 'abc'],
        ]);

        $row = DB::table('security_audit_log')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('sharepoint.ingest', $row->action);
        $this->assertSame('informationobject', $row->object_type);
        $this->assertSame(['sp_item_id' => 'abc'], json_decode((string) $row->details, true));
    }

    public function test_a_changed_setting_applies_to_the_same_long_lived_instance(): void
    {
        $svc = app(AuditService::class);
        $this->assertTrue($svc->isEnabled());

        $this->setting('audit_enabled', '0');
        $this->assertFalse($svc->isEnabled(), 'setting change ignored - still cached');
        $this->assertSame(0, $svc->log('create', 1));
    }
}
