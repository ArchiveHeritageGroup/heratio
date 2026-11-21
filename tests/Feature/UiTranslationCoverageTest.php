<?php

/**
 * UiTranslationCoverageTest - interface translation coverage counts ui_string
 * over the lang files, the fill skips what the site already shows translated
 * or a person signed off, writes only where it is told, and the reconcile
 * clears only unreviewed rows that hide or freeze the file (heratio#1416,
 * #1510).
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
use AhgCore\Services\UiCoverageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UiTranslationCoverageTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/ui-coverage-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function writeJson(string $file, array $data): void
    {
        file_put_contents($this->dir.'/'.$file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function read(string $file): array
    {
        return json_decode((string) file_get_contents($this->dir.'/'.$file), true) ?: [];
    }

    private function uiString(string $culture, string $key, string $value): void
    {
        DB::table('ui_string')->insert(['key' => $key, 'culture' => $culture, 'value' => $value, 'updated_at' => now()]);
    }

    private function approved(string $locale, string $key, string $value): void
    {
        DB::table('ui_string_change')->insert([
            'locale' => $locale, 'key_text' => $key, 'old_value' => null, 'new_value' => $value,
            'status' => 'approved', 'submitted_by_user_id' => 1, 'submitted_at' => now(),
        ]);
    }

    public function test_priority_order_puts_af_then_served_sa_languages_first(): void
    {
        $order = UiCoverageService::priorityOrder(['ar', 'zu', 'fr', 'af', 've', 'xh'], fn ($l) => $l !== 've');

        $this->assertSame(['af', 'zu', 'xh', 'ar', 'fr', 've'], $order);
        $this->assertSame(['af', 'zu', 've', 'ar'], UiCoverageService::priorityOrder(['ar', 'zu', 've', 'af']));
    }

    public function test_coverage_counts_what_the_site_shows(): void
    {
        $this->writeJson('zu.json', ['Save' => 'Gcina', 'Search' => 'Sesha', 'Edit' => 'Hlela', 'Delete' => 'Susa']);
        $this->writeJson('_meta.json', ['locales' => ['zu' => ['mt_same_as_source' => ['CSV']]]]);
        $this->uiString('zu', 'Save', 'Save');       // English stub hiding the file
        $this->uiString('zu', 'Search', 'Sesha');    // a frozen copy of the file
        $this->uiString('zu', 'Edit', 'Lungisa');    // differs, provenance unknown
        $this->uiString('zu', 'Delete', 'Susa');     // signed off in the editor
        $this->approved('zu', 'Delete', 'Susa');

        $rows = (new UiCoverageService($this->dir))->report(array_fill_keys(['Save', 'Search', 'Edit', 'Delete', 'CSV', 'Not yet'], true));

        $this->assertCount(1, $rows);
        $r = $rows[0];
        $this->assertTrue($r['sa']);
        $this->assertSame(3, $r['translated']);      // Search, Edit, Delete; Save shows English
        $this->assertSame(1, $r['same_as_source']);  // CSV
        $this->assertSame(2, $r['missing']);         // Save, Not yet
        $this->assertSame(1, $r['reviewed']);
        $this->assertSame(1, $r['db_hides_file']);
        $this->assertSame(1, $r['db_differs']);
        $this->assertSame(66.7, $r['pct']);

        $files = (new UiCoverageService($this->dir))->report(array_fill_keys(['Save', 'Search'], true), [], false);
        $this->assertSame(100.0, $files[0]['pct'], 'files only: the stub row is ignored');
    }

    public function test_reconcile_clears_only_unreviewed_rows_that_hide_or_freeze_the_file(): void
    {
        $this->writeJson('zu.json', ['Save' => 'Gcina', 'Search' => 'Sesha', 'Edit' => 'Hlela', 'Delete' => 'Susa']);
        $this->uiString('zu', 'Save', 'Save');
        $this->uiString('zu', 'Search', 'Sesha');
        $this->uiString('zu', 'Edit', 'Lungisa');
        $this->uiString('zu', 'Delete', 'Delete');
        $this->approved('zu', 'Delete', 'Delete');   // a reviewer kept the English
        $this->uiString('zu', 'Only here', 'Lapha kuphela');

        $this->artisan('ahg:translation-db-reconcile', ['--lang-dir' => $this->dir, '--locale' => ['zu']])->assertSuccessful();
        $this->assertSame(5, DB::table('ui_string')->where('culture', 'zu')->count(), 'a dry run deletes nothing');

        $this->artisan('ahg:translation-db-reconcile', ['--lang-dir' => $this->dir, '--locale' => ['zu'], '--apply' => true])->assertSuccessful();
        $left = DB::table('ui_string')->where('culture', 'zu')->orderBy('key')->pluck('value', 'key')->all();
        $this->assertSame(['Delete' => 'Delete', 'Edit' => 'Lungisa', 'Only here' => 'Lapha kuphela'], $left);
    }

    public function test_the_fill_skips_shown_and_signed_off_keys_and_writes_only_to_the_staging_dir(): void
    {
        $this->writeJson('en.json', ['Hold queue' => 'Hold queue', 'Queue item' => 'Queue item', 'Status text' => 'Status text', 'New label' => 'New label', 'Old label' => 'Old label']);
        $this->writeJson('af.json', ['Old label' => 'Ou etiket']);
        $this->writeJson('_meta.json', ['locales' => []]);
        $this->uiString('af', 'Hold queue', 'Hou-tou');      // already shown translated
        $this->uiString('af', 'Queue item', 'Queue item');   // English stub: still to fill
        $this->uiString('af', 'Status text', 'Status text'); // reviewer kept the English
        $this->approved('af', 'Status text', 'Status text');
        $before = sha1_file(base_path('lang/af.json'));

        $sent = [];
        Http::fake(['*/api/chat' => function (Request $req) use (&$sent) {
            $items = json_decode($req['messages'][1]['content'], true);
            $sent = array_values($items);
            $out = [];
            foreach ($items as $id => $en) {
                $out[$id] = ['Queue item' => 'Tou-item', 'New label' => 'Nuwe etiket'][$en] ?? $en;
            }

            return Http::response(['message' => ['content' => json_encode($out)]]);
        }]);

        $this->artisan('ahg:translation-llm-fill', ['locales' => 'af', '--lang-dir' => $this->dir])->assertSuccessful();
        $this->assertSame([], $sent, 'af stays refused without --allow-seed');

        $this->artisan('ahg:translation-llm-fill', ['locales' => 'af', '--lang-dir' => $this->dir, '--allow-seed' => 'af'])->assertSuccessful();
        $this->assertSame(['Queue item', 'New label'], $sent);
        $this->assertSame(['Old label' => 'Ou etiket', 'Queue item' => 'Tou-item', 'New label' => 'Nuwe etiket'], $this->read('af.json'));
        $this->assertSame(2, $this->read('_meta.json')['locales']['af']['mt_batch_count']);
        $this->assertSame($before, sha1_file(base_path('lang/af.json')), 'lang/af.json is not touched');
        $this->assertSame('Queue item', DB::table('ui_string')->where('culture', 'af')->where('key', 'Queue item')->value('value'), 'the fill writes files only');
    }

    public function test_the_admin_page_needs_an_admin_and_marks_the_sa_languages(): void
    {
        $this->assertAdminGated($this->get('/admin/ui-translation-coverage'));

        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'uicov-'.$id, 'email' => uniqid('uicov-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);

        $this->actingAs(User::findOrFail($id))->get('/admin/ui-translation-coverage?sa=1')
            ->assertOk()->assertSee('Interface translation coverage')->assertSee('South Africa')->assertSee('<code class="small">af</code>', false);
    }
}
