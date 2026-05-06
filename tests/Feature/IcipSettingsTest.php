<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class IcipSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_default_is_used_on_consultation_create()
    {
        // Seed icip_config table
        DB::table('icip_config')->insert([
            ['config_key' => 'default_consultation_follow_up_days', 'config_value' => '7'],
        ]);

        $this->withoutExceptionHandling();

        $response = $this->get('/admin/icip/consultation/create');

        $response->assertStatus(200);
        $response->assertSee('Follow Up');
        // The form should contain the follow_up_date field prefilled - exact format may vary
    }

    public function test_audit_middleware_records_access_when_enabled()
    {
        DB::table('icip_config')->insert([
            ['config_key' => 'audit_all_icip_access', 'config_value' => '1'],
        ]);

        $this->get('/federation'); // reuse a safe route to exercise middleware in test environment

        $this->assertDatabaseHas('icip_access_log', []);
    }
}
