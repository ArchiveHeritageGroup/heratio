<?php

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\MenuService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The RiC Tools menu can be hidden (ric_show_in_nav = 0) without switching RiC itself off. */
class RicNavSwitchTest extends TestCase
{
    private ?string $saved = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saved = DB::table('ahg_settings')->where('setting_key', 'ric_show_in_nav')->value('setting_value');
    }

    protected function tearDown(): void
    {
        $this->saved === null
            ? DB::table('ahg_settings')->where('setting_key', 'ric_show_in_nav')->delete()
            : DB::table('ahg_settings')->where('setting_key', 'ric_show_in_nav')->update(['setting_value' => $this->saved]);
        parent::tearDown();
    }

    private function set(string $v): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'ric_show_in_nav'], ['setting_value' => $v, 'setting_group' => 'fuseki']);
        if (method_exists(AhgSettingsService::class, 'clearCache')) {
            AhgSettingsService::clearCache();
        }
    }

    public function test_switch_hides_menu_but_not_ric(): void
    {
        if (! MenuService::isPluginEnabled('ahgRicExplorerPlugin')) {
            $this->markTestSkipped('RiC plugin not enabled on this database');
        }
        $this->set('1');
        $this->assertTrue(MenuService::ricInNav());
        $this->set('0');
        $this->assertFalse(MenuService::ricInNav());
        $this->assertTrue(MenuService::isPluginEnabled('ahgRicExplorerPlugin'));
    }
}
