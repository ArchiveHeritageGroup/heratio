<?php

/**
 * MarketplaceSwitchTest - one switch turns the marketplace off everywhere, and
 * selling is for staff unless public seller registration is open (27 Nov 2026).
 * Before, the banner and heritage buttons followed the plugin flag, the record
 * card followed marketplace_enabled, and "Sell" showed to every visitor.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceSwitchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['atom_plugin', 'marketplace_settings', 'ahg_settings'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not in this test database");
            }
        }
        DB::table('atom_plugin')->where('name', 'ahgMarketplacePlugin')->exists()
            ? DB::table('atom_plugin')->where('name', 'ahgMarketplacePlugin')->update(['is_enabled' => 1])
            : $this->markTestSkipped('marketplace plugin row not in this test database');
        DB::table('marketplace_settings')->updateOrInsert(['setting_key' => 'seller_registration_open'], ['setting_value' => '0', 'setting_type' => 'boolean', 'setting_group' => 'general']);
        AhgSettingsService::set('marketplace_enabled', '1', 'marketplace');
    }

    private function editor(): User
    {
        \AhgCore\Services\AclService::forgetUser();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'mkt-'.$id, 'email' => uniqid('mkt-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 101]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_guests_browse_but_do_not_sell_when_registration_is_closed(): void
    {
        $this->assertTrue(MenuService::marketplaceEnabled());
        $this->assertFalse(MenuService::marketplaceSellingAllowed());
    }

    public function test_staff_may_sell(): void
    {
        $this->actingAs($this->editor());
        $this->assertTrue(MenuService::marketplaceSellingAllowed());
    }

    public function test_the_switch_turns_everything_off(): void
    {
        AhgSettingsService::set('marketplace_enabled', '0', 'marketplace');
        $this->actingAs($this->editor());
        $this->assertFalse(MenuService::marketplaceEnabled());
        $this->assertFalse(MenuService::marketplaceSellingAllowed());
    }
}
