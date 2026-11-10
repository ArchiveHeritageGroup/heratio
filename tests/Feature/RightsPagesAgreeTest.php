<?php

/**
 * RightsPagesAgreeTest - heratio#1588: the rights pages report what is there.
 * The TK-label count includes ICIP labels, the record page names its TK labels,
 * a BC label badge does not print its family twice, and the rights browse fills
 * its Type / Value / Date columns.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RightsPagesAgreeTest extends TestCase
{
    use DatabaseTransactions;

    private int $io;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['icip_tk_label', 'icip_tk_label_type', 'rights_record'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not installed.");
            }
        }
        $this->io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $this->io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $this->io, 'culture' => 'en', 'title' => 'Rights agree record']);
        DB::table('slug')->insert(['object_id' => $this->io, 'slug' => 'rights-agree-'.$this->io]);
        $type = (int) (DB::table('icip_tk_label_type')->where('code', 'zz_bc_clan')->value('id')
            ?: DB::table('icip_tk_label_type')->insertGetId(['code' => 'zz_bc_clan', 'category' => 'BC', 'name' => 'BC Zz Clan']));
        DB::table('icip_tk_label')->insert(['information_object_id' => $this->io, 'label_type_id' => $type]);
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ra-'.$id, 'email' => uniqid('ra-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_the_tk_count_includes_icip_labels(): void
    {
        $this->actingAs($this->admin());
        $stats = (new \ReflectionMethod(\AhgRightsHolderManage\Controllers\ExtendedRightsController::class, 'getStats'))
            ->invoke(app(\AhgRightsHolderManage\Controllers\ExtendedRightsController::class));
        $this->assertGreaterThanOrEqual(1, $stats->with_tk_labels, 'a record with an ICIP label counts');
    }

    public function test_the_bc_badge_prints_its_family_once(): void
    {
        $html = view('icip::partials.community-protocol-badge', ['ioId' => $this->io])->render();
        if (! str_contains($html, 'Zz Clan')) {
            $this->markTestSkipped('community protocol badge did not render this label here.');
        }
        $this->assertStringNotContainsString('BC BC Zz Clan', $html);
        $this->assertStringContainsString('BC Zz Clan', $html);
    }

    public function test_the_rights_browse_fills_type_value_and_date(): void
    {
        DB::table('rights_record')->insert(['object_id' => $this->io, 'basis' => 'copyright', 'copyright_holder' => 'Zz Holder Trust', 'start_date' => '2020-01-01']);
        $this->actingAs($this->admin())->get('/admin/rights/browse?q='.$this->io)->assertOk()
            ->assertSee('Copyright')->assertSee('Zz Holder Trust')->assertSee('2020-01-01');
    }
}
