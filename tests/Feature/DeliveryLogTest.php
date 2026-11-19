<?php

/**
 * DeliveryLogTest - heratio#1739 / #1772. Views and downloads are recorded
 * where they are delivered, for signed-out visitors too: a usage event for
 * the statistics, an audit row when the audit settings ask for it; a repeat
 * within the session window is one delivery; bots are flagged and left out
 * of the reports and the audit; the requests report counts requests,
 * decisions and releases.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\DeliveryLog;
use AhgCore\Support\StatusRow;
use AhgStatistics\Services\StatisticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryLogTest extends TestCase
{
    use DatabaseTransactions;

    private int $io;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ahg_usage_event', 'security_audit_log', 'digital_object'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present");
            }
        }
        Cache::flush();
        foreach (['audit_enabled' => '1', 'audit_views' => '1', 'audit_downloads' => '1'] as $k => $v) {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => $k], ['setting_value' => $v, 'setting_group' => 'audit']);
        }
        DB::table('ahg_settings')->where('setting_group', 'statistics')->whereIn('setting_key', ['track_authenticated_only', 'bot_filtering_enabled'])->delete();

        $this->io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $this->io, 'source_culture' => 'en', 'lft' => 3000000, 'rgt' => 3000001]);
        StatusRow::set($this->io, 158, 160);
        $this->dir = 'uploads/r/delivery-'.uniqid();
        $master = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $master, 'object_id' => $this->io, 'usage_id' => 140, 'name' => 'scan.jpg', 'path' => '/'.$this->dir.'/', 'mime_type' => 'image/jpeg']);
        $ref = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $ref, 'parent_id' => $master, 'usage_id' => 141, 'name' => 'reference_scan.jpg', 'path' => '/'.$this->dir.'/', 'mime_type' => 'image/jpeg']);
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function events(string $type): \Illuminate\Support\Collection
    {
        return DB::table('ahg_usage_event')->where('object_id', $this->io)->where('event_type', $type)->get();
    }

    private function audits(string $action): int
    {
        return DB::table('security_audit_log')->where('object_id', $this->io)->where('action', $action)->count();
    }

    private function fetch(string $name, string $ua = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0')
    {
        return $this->withHeaders(['X-Original-URI' => '/'.$this->dir.'/'.$name, 'User-Agent' => $ua])->get(route('io.privacy.file-check'));
    }

    public function test_a_signed_out_download_is_recorded_once_where_the_file_is_served(): void
    {
        $this->fetch('scan.jpg')->assertNoContent();
        $this->fetch('scan.jpg')->assertNoContent(); // a viewer's second range request
        $this->fetch('reference_scan.jpg')->assertNoContent(); // the preview is part of a view, not a download

        $ev = $this->events('download');
        $this->assertCount(1, $ev);
        $this->assertNull($ev[0]->user_id);
        $this->assertSame(0, (int) $ev[0]->is_bot);
        $this->assertSame(1, $this->audits('download'));
        $this->assertStringContainsString('scan.jpg', (string) DB::table('security_audit_log')->where('object_id', $this->io)->where('action', 'download')->value('details'));
    }

    public function test_a_bot_is_flagged_and_kept_out_of_reports_and_audit(): void
    {
        $this->fetch('scan.jpg', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertNoContent();

        $ev = $this->events('download');
        $this->assertCount(1, $ev);
        $this->assertSame(1, (int) $ev[0]->is_bot);
        $this->assertSame(0, $this->audits('download'));
        $today = now()->toDateString();
        $this->assertSame(0, (int) collect((new StatisticsService)->getItemStats($this->io, $today, $today))->get('downloads'));
    }

    public function test_views_are_counted_per_visitor_per_window(): void
    {
        DeliveryLog::view($this->io);
        DeliveryLog::view($this->io);
        $this->assertCount(1, $this->events('view'));
        $this->assertSame(1, $this->audits('view'));

        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'dl-'.$id, 'email' => uniqid('dl-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        $this->actingAs(User::findOrFail($id));
        DeliveryLog::view($this->io);
        $this->assertCount(2, $this->events('view'), 'a different visitor counts');
        $this->assertSame($id, (int) $this->events('view')->last()->user_id);
    }

    public function test_the_requests_report_counts_requests_decisions_and_releases(): void
    {
        $uid = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $uid, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $uid, 'username' => 'rr-'.$uid, 'email' => uniqid('rr-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        $svc = app(\AhgAccessRequest\Services\AccessRequestService::class);
        $a = $svc->createRequest($uid, ['request_type' => 'object', 'scope_type' => 'basket', 'reason' => 'Research', 'items' => [['id' => $this->io, 'title' => 'x']]]);
        $b = $svc->createRequest($uid, ['request_type' => 'object', 'reason' => 'Research', 'target_object_id' => $this->io]);
        $svc->approveRequest($a, 1, null, 'Credit the archive');
        $svc->denyRequest($b, 1, 'No');

        $today = now()->toDateString();
        $data = (new StatisticsService)->getRequestStats($today, $today);

        $this->assertGreaterThanOrEqual(2, $data['requests']['submitted']);
        $this->assertGreaterThanOrEqual(1, $data['requests']['basket']);
        $this->assertGreaterThanOrEqual(1, $data['decisions']['released with conditions'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $data['decisions']['denied'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $data['releases']['by_request'] ?? 0);

        $admin = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $admin, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $admin, 'username' => 'ra-'.$admin, 'email' => uniqid('ra-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $admin, 'group_id' => 100]);
        AclService::forgetUser();
        $this->actingAs(User::findOrFail($admin))->get(route('statistics.requests'))->assertOk()->assertSee('Release links issued');
    }
}
