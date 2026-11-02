<?php

/**
 * InstallPingTest - the opt-in install ping (heratio#1571) stores one row per
 * random install id, bumps it on a reinstall, rejects anything that is not
 * the three plain fields, and keeps no caller address.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallPingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('heratio_install_ping')) {
            $this->markTestSkipped('heratio_install_ping not migrated in this install (#1571).');
        }
    }

    public function test_a_ping_stores_one_row_and_a_reinstall_bumps_it(): void
    {
        $id = (string) Str::uuid();
        $ping = ['install_id' => $id, 'version' => '1.164.41', 'arch' => 'x86_64', 'source' => 'installer'];

        $this->postJson('/api/v1/install-ping', $ping)->assertNoContent();
        $this->postJson('/api/v1/install-ping', ['version' => '1.164.42'] + $ping)->assertNoContent();

        $rows = DB::table('heratio_install_ping')->where('install_id', $id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows[0]->pings);
        $this->assertSame('1.164.42', $rows[0]->version);
        $this->assertSame(['install_id', 'version', 'arch', 'source', 'pings', 'first_seen', 'last_seen'],
            Schema::getColumnListing('heratio_install_ping'), 'no column for an address or a name');
    }

    public function test_anything_but_the_plain_fields_is_refused(): void
    {
        $this->postJson('/api/v1/install-ping', ['install_id' => 'not-a-uuid', 'version' => '1', 'arch' => 'x86_64'])->assertStatus(422);
        $this->postJson('/api/v1/install-ping', ['install_id' => (string) Str::uuid(), 'version' => 'acme corp', 'arch' => 'x86_64'])->assertStatus(422);
        $this->postJson('/api/v1/install-ping', ['install_id' => (string) Str::uuid(), 'version' => '1', 'arch' => 'x86_64', 'source' => 'other'])->assertStatus(422);
    }
}
