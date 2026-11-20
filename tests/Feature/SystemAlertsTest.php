<?php

/**
 * SystemAlertsTest - heratio#1742. A failing check alerts administrators once,
 * stays quiet while it keeps failing, and says so again when it recovers; the
 * monitor runs its checks and reports a healthy site as healthy.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\SystemAlerts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemAlertsTest extends TestCase
{
    use DatabaseTransactions;

    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::forget('system-alert:disk:/zz-test');
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'sa-'.$id, 'email' => uniqid('sa-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        $this->admin = $id;
    }

    private function notices(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_notification')->where('user_id', $this->admin)->whereIn('type', ['system_alert', 'system_recovered'])->orderBy('id')->pluck('title');
    }

    public function test_an_alert_is_raised_once_and_recovery_is_reported(): void
    {
        $this->assertSame('raised', SystemAlerts::report('disk:/zz-test', false, '3% free'));
        $this->assertSame('', SystemAlerts::report('disk:/zz-test', false, '3% free'), 'no repeat within the reminder window');
        $this->assertSame('recovered', SystemAlerts::report('disk:/zz-test', true, '40% free'));
        $this->assertSame('', SystemAlerts::report('disk:/zz-test', true), 'healthy stays quiet');

        $titles = $this->notices()->all();
        $this->assertCount(2, $titles);
        $this->assertStringStartsWith('Problem: disk space on /zz-test', $titles[0]);
        $this->assertStringStartsWith('Recovered: disk space on /zz-test', $titles[1]);
    }

    public function test_the_monitor_reports_a_healthy_site(): void
    {
        Http::fake(['*/up' => Http::response('ok', 200), '*/_cluster/health' => Http::response(['status' => 'green'])]);
        $code = Artisan::call('ahg:monitor', ['--dry-run' => true]);
        $out = Artisan::output();

        $this->assertStringContainsString('web', $out);
        $this->assertStringContainsString('database', $out);
        $this->assertStringNotContainsString('FAIL', $out);
        $this->assertSame(0, $code);
    }
}
