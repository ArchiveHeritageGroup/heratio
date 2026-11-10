<?php

/**
 * SharePointTenantAddTest - registering the same app registration twice
 * updates the tenant (new secret, label) instead of failing on the unique key.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointTenantAddTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_second_run_updates_the_tenant(): void
    {
        if (! Schema::hasTable('sharepoint_tenant')) {
            $this->markTestSkipped('sharepoint_tenant missing');
        }
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600])]);
        $t = '33333333-3333-3333-3333-333333333333';
        $c = '44444444-4444-4444-4444-444444444444';
        // A run cut short before its secret was stored.
        DB::table('sharepoint_tenant')->insert(['name' => 'old', 'tenant_id' => $t, 'client_id' => $c, 'client_secret_ref' => 'pending',
            'webhook_client_state' => 'x', 'status' => 'error']);

        $this->artisan('sharepoint:tenant-add', ['--name' => 'Demo', '--tenant-id' => $t, '--client-id' => $c])
            ->expectsQuestion('Client secret VALUE (not the secret ID; input is hidden)', 'the-new-secret-value')
            ->assertSuccessful();

        $rows = DB::table('sharepoint_tenant')->where('tenant_id', $t)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['Demo', 'active'], [$rows[0]->name, $rows[0]->status]);
        $stored = DB::table('ahg_settings')->where('setting_group', 'sharepoint')->where('setting_key', $rows[0]->client_secret_ref)->value('setting_value');
        $this->assertSame('the-new-secret-value', Crypt::decryptString($stored));
    }
}
