<?php

/**
 * AcquisitionTrackingTest - H09: the demo site records visits, clicks and
 * enquiries with their source, refuses crawlers and malformed sources, stores
 * no personal detail, does nothing on client installs, and the weekly
 * dashboard adds it up.
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

namespace Tests\Feature;

use AhgCore\Models\User;
use App\Services\AcquisitionService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcquisitionTrackingTest extends TestCase
{
    use DatabaseTransactions;

    private const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_acquisition_event')) {
            $this->markTestSkipped('ahg_acquisition_event not present; run the migrations.');
        }
        $this->withoutMiddleware(VerifyCsrfToken::class);
        DB::table('ahg_acquisition_event')->delete();
    }

    private function beacon(array $data, string $ua = self::BROWSER)
    {
        return $this->withHeader('User-Agent', $ua)->post(route('acquisition.event'), $data);
    }

    public function test_the_demo_site_records_events_with_their_source_and_no_personal_detail(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);

        $this->beacon(['event' => 'visit', 'source' => 'github', 'path' => '/', 'visit' => 'a1b2c3d4e5f60718'])->assertNoContent();
        $this->beacon(['event' => 'demo', 'source' => 'github', 'path' => '/', 'detail' => '/glam/browse', 'visit' => 'a1b2c3d4e5f60718'])->assertNoContent();

        $rows = DB::table('ahg_acquisition_event')->orderBy('id')->get();
        $this->assertSame(['visit', 'demo'], $rows->pluck('event')->all());
        $this->assertSame('github', $rows[0]->source);
        $this->assertSame(['id', 'occurred_at', 'event', 'source', 'detail', 'path', 'visit_id'], array_keys((array) $rows[0]),
            'no IP, user agent or personal column');
    }

    public function test_crawlers_bad_sources_unknown_events_and_client_installs_are_not_recorded(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);
        $this->beacon(['event' => 'visit', 'source' => 'search'], 'Mozilla/5.0 (compatible; Googlebot/2.1)')->assertNoContent();
        $this->beacon(['event' => 'visit', 'source' => 'https://evil.example/?x=<script>'])->assertNoContent();
        $this->beacon(['event' => 'enquiry', 'source' => 'direct'])->assertSessionHasErrors('event');

        config(['heratio.homepage_mode' => 'institutional']);
        $this->beacon(['event' => 'visit', 'source' => 'direct'])->assertNoContent();

        $rows = DB::table('ahg_acquisition_event')->get();
        $this->assertCount(1, $rows, 'only the malformed-source visit is kept, recorded as direct');
        $this->assertSame('direct', $rows[0]->source);
    }

    public function test_a_contact_enquiry_is_counted_with_its_type_and_source(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Ada Example', 'email' => 'ada@example.org', 'message' => 'We would like to deploy Heratio.',
            'enquiry_type' => 'deployment', 'acq_source' => 'referral:example.org',
        ])->assertRedirect(route('contact.show'));

        $row = DB::table('ahg_acquisition_event')->where('event', 'enquiry')->first();
        $this->assertSame('deployment', $row->detail);
        $this->assertSame('referral:example.org', $row->source);
        $this->assertStringNotContainsString('ada', strtolower(json_encode($row)), 'no personal detail stored');
    }

    public function test_the_weekly_dashboard_adds_up_events_by_week_source_and_enquiry_type(): void
    {
        $svc = app(AcquisitionService::class);
        foreach (['visit', 'visit', 'visit', 'demo', 'github', 'enquiry'] as $e) {
            $svc->record($e, 'search', $e === 'enquiry' ? 'hosting' : null);
        }
        $svc->record('visit', 'social');

        $week = now()->startOfWeek()->toDateString();
        $this->assertSame(4, $svc->weekly(4)[$week]['visit']);
        $this->assertSame(1, $svc->weekly(4)[$week]['enquiry']);
        $this->assertSame(3, $svc->bySource(now()->subDay(), now()->addDay())['search']['visit']);

        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'acq-'.$id, 'email' => uniqid('acq-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        $this->get(route('acquisition.dashboard'))->assertRedirect();
        $this->actingAs(User::findOrFail($id))->get(route('acquisition.dashboard'))->assertOk()
            ->assertSee('By week')->assertSee('search')->assertSee('Hosting by The Archive and Heritage Group');
    }
}
