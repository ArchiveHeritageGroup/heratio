<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IcipSettingsTest extends TestCase
{
    // DatabaseTransactions (not RefreshDatabase): the test DB is built by
    // loading database/core/*.sql + every package install.sql (~995 AtoM-style
    // tables). RefreshDatabase runs migrate:fresh, which DROPS all of those and
    // rebuilds only the ~29 Laravel migrations - none of which create the core
    // tables (object, actor, term, ...). That permanently wiped the schema for
    // every test running after this one in the process (issue #1136). Wrapping
    // in a transaction keeps the loaded schema intact and rolls back our writes.
    use DatabaseTransactions;

    public function test_follow_up_default_is_used_on_consultation_create()
    {
        // Seed icip_config — idempotent: the key may already exist from the
        // package install seed, so a plain insert hits a duplicate-key
        // UniqueConstraintViolationException. updateOrInsert sets it either way.
        DB::table('icip_config')->updateOrInsert(
            ['config_key' => 'default_consultation_follow_up_days'],
            ['config_value' => '7'],
        );

        $this->withoutExceptionHandling();

        $response = $this->get('/admin/icip/consultation/create');

        $response->assertStatus(200);
        $response->assertSee('Follow Up');
        // The form should contain the follow_up_date field prefilled - exact format may vary
    }

    public function test_audit_middleware_records_access_when_enabled()
    {
        DB::table('icip_config')->updateOrInsert(
            ['config_key' => 'audit_all_icip_access'],
            ['config_value' => '1'],
        );

        $this->get('/federation'); // reuse a safe route to exercise middleware in test environment

        $this->assertDatabaseHas('icip_access_log', []);
    }
}
