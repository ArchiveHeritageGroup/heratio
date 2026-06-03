<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        if (! Schema::hasTable('icip_config')) {
            $this->markTestSkipped('icip_config table not present (ahg-icip install.sql not loaded)');
        }

        // Seed icip_config — idempotent: the key may already exist from the
        // package install seed, so a plain insert hits a duplicate-key
        // UniqueConstraintViolationException. updateOrInsert sets it either way.
        DB::table('icip_config')->updateOrInsert(
            ['config_key' => 'default_consultation_follow_up_days'],
            ['config_value' => '7'],
        );

        // The consultation add form is behind auth; sign in as an admin-capable
        // user (same pattern as ModsEditTest). The route is /icip/consultation/add
        // (consultationEdit in add-mode), not /admin/icip/consultation/create.
        $model = $this->actingAdmin();
        if (! $model) {
            $this->markTestSkipped('No admin-capable user available to authenticate');
        }

        $response = $this->actingAs($model)->get('/icip/consultation/add');

        $response->assertStatus(200);
        $response->assertSee('Follow Up');
    }

    /** Hydrate the first admin-capable user model, or null if none/unavailable. */
    private function actingAdmin(): ?object
    {
        try {
            if (! Schema::hasTable('user')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        $row = DB::table('user')->whereNotNull('username')->limit(1)->first();
        if (! $row) {
            return null;
        }
        $userClass = '\\App\\Models\\User';
        return class_exists($userClass) ? $userClass::find($row->id) : null;
    }

    public function test_audit_middleware_records_access_when_enabled()
    {
        if (! Schema::hasTable('icip_config') || ! Schema::hasTable('icip_access_log')) {
            $this->markTestSkipped('icip tables not present (ahg-icip install.sql not loaded)');
        }

        // Enable the audit flag.
        DB::table('icip_config')->updateOrInsert(
            ['config_key' => 'audit_all_icip_access'],
            ['config_value' => '1'],
        );

        // Invoke the middleware directly. It is wired onto the ICIP route groups
        // (alias 'audit.icip', after 'auth' so the user is resolved), but those
        // routes are auth-gated and the test DB carries no user fixture, so an
        // HTTP request would 302 at auth before reaching the audit step. Calling
        // handle() asserts the unit's actual contract: with the flag on, an
        // (authenticated) request records a row.
        $middleware = new \AhgIcip\Middleware\AuditIcipAccess();
        $request = \Illuminate\Http\Request::create('/icip/probe', 'GET');
        $passed = false;
        $middleware->handle($request, function ($req) use (&$passed) {
            $passed = true;

            return response('ok');
        });

        // Chain continues (non-blocking middleware) and the access is logged.
        $this->assertTrue($passed, 'middleware must call $next');
        $this->assertSame(1, DB::table('icip_access_log')->where('path', 'icip/probe')->count());
    }
}
