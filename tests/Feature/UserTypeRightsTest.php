<?php

/**
 * UserTypeRightsTest - heratio#1769 / #1770. A user's type (subscription,
 * else approved registration, else General Public) decides what they may do
 * and pay: registrations are classified by email domain and approved once the
 * address is confirmed; remote access opens protected originals; a type that
 * may not request reproductions cannot check out; prices follow the type's
 * discount or free access; a subscription grants a type for its period.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\MasterAccess;
use AhgCore\Services\UserType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTypeRightsTest extends TestCase
{
    use DatabaseTransactions;

    private array $types = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasColumn('research_researcher_type', 'free_access') || ! Schema::hasTable('ahg_user_subscription')) {
            $this->markTestSkipped('User type columns not migrated in this database.');
        }
        Mail::fake();
        UserType::forget();
        MasterAccess::forget();
        AclService::forgetUser();
        DB::table('research_researcher_type')->update(['email_domains' => null]);
        DB::table('research_researcher_type')->whereIn('code', ['public', 'zz_uni', 'zz_staff', 'zz_media', 'zz_none'])->delete();
        $this->types['public'] = $this->type('public', ['can_request_reproductions' => 1]);
        $this->types['uni'] = $this->type('zz_uni', ['auto_approve' => 1, 'requires_id_verification' => 0, 'email_domains' => 'uni.example', 'discount_percent' => 25]);
        $this->types['staff'] = $this->type('zz_staff', ['can_remote_access' => 1, 'free_access' => 1]);
        $this->types['media'] = $this->type('zz_media', ['auto_approve' => 0, 'email_domains' => 'press.example']);
        $this->types['none'] = $this->type('zz_none', ['can_request_reproductions' => 0]);
    }

    protected function tearDown(): void
    {
        UserType::forget();
        MasterAccess::forget();
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function type(string $code, array $over): int
    {
        return (int) DB::table('research_researcher_type')->insertGetId($over + [
            'name' => 'Type '.$code, 'code' => $code, 'can_remote_access' => 0, 'can_request_reproductions' => 1, 'can_export_data' => 1,
            'requires_id_verification' => 1, 'auto_approve' => 0, 'expiry_months' => 12, 'is_active' => 1, 'free_access' => 0, 'discount_percent' => 0,
        ]);
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(?string $email = null): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ut-'.$id, 'email' => $email ?? uniqid('ut-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function researcher(User $u, int $typeId): void
    {
        DB::table('research_researcher')->insert(['user_id' => $u->id, 'first_name' => 'A', 'last_name' => 'B', 'email' => $u->email,
            'researcher_type_id' => $typeId, 'status' => 'approved', 'expires_at' => now()->addYear()->toDateString(), 'created_at' => now()]);
        UserType::forget();
    }

    private function register(User $u, string $email, string $affiliation = 'independent'): int
    {
        return app(\AhgResearch\Services\ResearchService::class)->registerResearcher([
            'user_id' => $u->id, 'first_name' => 'Ann', 'last_name' => 'Reader', 'email' => $email, 'affiliation_type' => $affiliation,
        ]);
    }

    public function test_registration_is_classified_by_domain_and_approved_once_the_address_is_confirmed(): void
    {
        $u = $this->user('ann@dept.uni.example');
        $rid = $this->register($u, 'ann@dept.uni.example');

        $r = DB::table('research_researcher')->where('id', $rid)->first();
        $this->assertSame($this->types['uni'], (int) $r->researcher_type_id);
        $this->assertSame('pending', $r->status, 'not approved on an address nobody has confirmed');
        $this->assertNotEmpty($r->email_verify_token);
        $this->assertSame($this->types['public'], (int) UserType::forUser((int) $u->id)->id);

        $this->get(route('research.confirmEmail', $r->email_verify_token))->assertRedirect();

        $r = DB::table('research_researcher')->where('id', $rid)->first();
        $this->assertSame('approved', $r->status);
        $this->assertNotNull($r->email_verified_at);
        $this->assertNull($r->email_verify_token);
        UserType::forget();
        $this->assertSame($this->types['uni'], (int) UserType::forUser((int) $u->id)->id);
        $this->get(route('research.confirmEmail', str_repeat('a', 48)))->assertRedirect(route('homepage'));
    }

    public function test_a_type_that_needs_review_is_classified_but_stays_pending(): void
    {
        $u = $this->user('cub@press.example');
        $rid = $this->register($u, 'cub@press.example');

        $r = DB::table('research_researcher')->where('id', $rid)->first();
        $this->assertSame($this->types['media'], (int) $r->researcher_type_id);
        $this->assertSame('pending', $r->status);
        $this->assertNull($r->email_verify_token, 'no auto approval, so nothing to confirm');
    }

    public function test_remote_access_opens_protected_originals(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => MasterAccess::SETTING], ['setting_value' => 'true', 'setting_group' => 'security']);
        AhgSettingsService::clearCache();
        $io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en', 'lft' => 4000000, 'rgt' => 4000001]);

        $plain = $this->user();
        $this->researcher($plain, $this->types['uni']);
        $this->assertFalse(MasterAccess::mayFetch($io, $plain));

        $remote = $this->user();
        $this->researcher($remote, $this->types['staff']);
        MasterAccess::forget();
        $this->assertTrue(MasterAccess::mayFetch($io, $remote));
    }

    public function test_prices_follow_the_type_and_a_type_without_reproductions_cannot_check_out(): void
    {
        $uni = $this->user();
        $this->researcher($uni, $this->types['uni']);
        $free = $this->user();
        $this->researcher($free, $this->types['staff']);

        $this->assertSame(75.0, UserType::price((int) $uni->id, 100));
        $this->assertSame(0.0, UserType::price((int) $free->id, 100));
        $this->assertSame(100.0, UserType::price(null, 100), 'a guest pays the list price');

        $blocked = $this->user();
        $this->researcher($blocked, $this->types['none']);
        DB::table('cart')->insert(['user_id' => $blocked->id, 'archival_description_id' => 1, 'archival_description' => 'x', 'slug' => 'x', 'created_at' => now()]);
        $this->actingAs($blocked)->post(route('cart.checkout'), ['rtp_name' => 'A', 'rtp_surname' => 'B', 'rtp_email' => 'a@example.test'])
            ->assertRedirect(route('cart.browse'))->assertSessionHas('error');
        $this->assertSame(0, DB::table('access_request')->where('user_id', $blocked->id)->count());
    }

    public function test_a_subscription_grants_its_type_for_its_period(): void
    {
        $u = $this->user();
        $this->assertSame($this->types['public'], (int) UserType::forUser((int) $u->id)->id);
        $tier = DB::table('ahg_subscription_tier')->insertGetId(['name' => 'ZZ annual', 'researcher_type_id' => $this->types['staff'], 'price' => 120, 'period_months' => 12, 'is_active' => 1]);
        $sub = DB::table('ahg_user_subscription')->insertGetId(['user_id' => $u->id, 'tier_id' => $tier, 'status' => 'pending']);
        $svc = app(\AhgCart\Services\EcommerceService::class);

        $order = $svc->createOrderForSubscription($sub);
        $this->assertSame($sub, (int) DB::table('ahg_order')->where('id', $order)->value('subscription_id'));
        $this->assertSame('120.00', (string) DB::table('ahg_order')->where('id', $order)->value('total'));
        UserType::forget();
        $this->assertSame($this->types['public'], (int) UserType::forUser((int) $u->id)->id, 'not before payment');

        $svc->activateSubscription($sub);
        $this->assertSame($this->types['staff'], (int) UserType::forUser((int) $u->id)->id);
        $row = DB::table('ahg_user_subscription')->where('id', $sub)->first();
        $this->assertSame('active', $row->status);
        $this->assertTrue(\Illuminate\Support\Carbon::parse($row->ends_at)->between(now()->addMonths(12)->subDay(), now()->addMonths(12)->addDay()));

        DB::table('ahg_user_subscription')->where('id', $sub)->update(['ends_at' => now()->subMinute()]);
        UserType::forget();
        $this->assertSame($this->types['public'], (int) UserType::forUser((int) $u->id)->id, 'lapses at the end of its period');
    }
}
