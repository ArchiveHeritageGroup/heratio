<?php

/**
 * CustomFieldsEndToEndTest - a custom field value from form to export.
 *
 * heratio#1530: values were never stored, shown or exported (the service
 * queried columns its own schema lacks). A value saved on the description
 * edit form now appears on the show page, in EAD 2002 / EAD3 (<odd>), DC,
 * MODS and the CSV export; a field marked internal stays out of the exports.
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
use AhgCustomFields\Services\CustomFieldService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomFieldsEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    private CustomFieldService $svc;

    private int $io;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CustomFieldService::class);
        DB::table('custom_field_definition')->update(['is_active' => 0]);   // only ours count (rolled back)

        $this->svc->createDefinition(['field_key' => 'grant_number', 'field_label' => 'ZZ Grant number', 'field_type' => 'text', 'entity_type' => 'informationobject', 'sort_order' => 1]);
        $this->svc->createDefinition(['field_key' => '', 'field_label' => 'ZZ Internal note', 'field_type' => 'textarea', 'entity_type' => 'information_object', 'include_in_export' => 0, 'sort_order' => 2]);
        $this->svc->createDefinition(['field_key' => 'digitised', 'field_label' => 'ZZ Digitised', 'field_type' => 'boolean', 'entity_type' => 'informationobject', 'sort_order' => 3]);
        $this->svc->createDefinition(['field_key' => 'year_count', 'field_label' => 'ZZ Year count', 'field_type' => 'number', 'entity_type' => 'informationobject', 'sort_order' => 4]);

        $this->io = Io::create(['title' => 'ZZ custom fields test'], 'en');
        \AhgCore\Support\StatusRow::set($this->io, 158, 160);
        $this->slug = (string) DB::table('slug')->where('object_id', $this->io)->value('slug');
    }

    private function save(array $cf)
    {
        return $this->actingAs($this->makeAdmin())->put(route('informationobject.update', $this->slug), [
            'title' => 'ZZ custom fields test',
            CustomFieldService::FORM_MARKER => '1',
            'cf' => $cf,
        ]);
    }

    public function test_the_alias_entity_key_and_a_generated_field_key_are_normalised(): void
    {
        $def = DB::table('custom_field_definition')->where('field_label', 'ZZ Internal note')->first();

        $this->assertSame('informationobject', $def->entity_type);
        $this->assertSame('zz_internal_note', $def->field_key);
    }

    public function test_a_value_saved_on_the_edit_form_is_stored_and_shown(): void
    {
        $this->save(['grant_number' => 'NRF-2026-0042', 'zz_internal_note' => 'Check with donor', 'digitised' => '1', 'year_count' => '12'])
            ->assertRedirect();

        $values = collect($this->svc->exportValues($this->io, CustomFieldService::IO, false))->pluck('value', 'key');
        $this->assertSame('NRF-2026-0042', $values['grant_number']);
        $this->assertSame('Check with donor', $values['zz_internal_note']);
        $this->assertSame('Yes', $values['digitised']);
        $this->assertSame('12', $values['year_count']);

        $html = view('ahg-custom-fields::partials._view-fields', ['objectId' => $this->io])->render();
        $this->assertStringContainsString('ZZ Grant number', $html);
        $this->assertStringContainsString('NRF-2026-0042', $html);
    }

    public function test_blanking_a_value_removes_it_and_an_unticked_box_is_stored_as_no(): void
    {
        $this->save(['grant_number' => 'NRF-1', 'digitised' => '1']);
        $this->save(['grant_number' => '']);

        $values = collect($this->svc->exportValues($this->io, CustomFieldService::IO, false))->pluck('value', 'key');
        $this->assertArrayNotHasKey('grant_number', $values->all());
        $this->assertSame('No', $values['digitised']);
    }

    public function test_an_invalid_value_is_refused_with_the_field_error(): void
    {
        $this->save(['year_count' => 'twelve'])->assertSessionHasErrors('cf.year_count');

        $this->assertSame([], $this->svc->exportValues($this->io, CustomFieldService::IO, false));
    }

    public function test_exported_values_appear_in_ead_dc_mods_and_csv_and_internal_fields_do_not(): void
    {
        $this->save(['grant_number' => 'NRF-2026-0042', 'zz_internal_note' => 'Check with donor']);

        $ead = $this->get(route('informationobject.export.ead', $this->slug))->assertOk()->getContent();
        $this->assertStringContainsString('<odd type="grant_number"><head>ZZ Grant number</head><p>NRF-2026-0042</p></odd>', $ead);
        $this->assertStringNotContainsString('Check with donor', $ead);

        $ead3 = $this->get(route('informationobject.export.ead3', $this->slug))->assertOk()->getContent();
        $this->assertStringContainsString('<odd localtype="grant_number">', $ead3);

        $this->assertStringContainsString('<dc:description>ZZ Grant number: NRF-2026-0042</dc:description>',
            $this->get(route('informationobject.export.dc', $this->slug))->assertOk()->getContent());
        $this->assertStringContainsString('displayLabel="ZZ Grant number">NRF-2026-0042</note>',
            $this->get(route('informationobject.export.mods', $this->slug))->assertOk()->getContent());

        $csv = $this->get(route('informationobject.export.csv', $this->slug))->assertOk()->streamedContent();
        $this->assertStringContainsString('cf_grant_number', $csv);
        $this->assertStringContainsString('NRF-2026-0042', $csv);
        $this->assertStringNotContainsString('cf_zz_internal_note', $csv);
    }

    public function test_the_finding_aid_ead_carries_the_values(): void
    {
        $this->save(['grant_number' => 'NRF-2026-0042']);

        $job = new \App\Jobs\FindingAidJob($this->io);
        $odd = (new \ReflectionMethod($job, 'customFieldsOdd'))->invoke($job, $this->svc->exportValues($this->io), '  ');
        $html = (new \ReflectionMethod($job, 'eadToHtml'))->invoke($job, (object) ['title' => 'T'], "<archdesc>{$odd}</archdesc>");

        $this->assertStringContainsString('<h3>ZZ Grant number</h3><p>NRF-2026-0042</p>', $html);
    }

    public function test_the_admin_form_creates_a_definition_and_refuses_a_duplicate_key(): void
    {
        $admin = $this->makeAdmin();
        $form = ['field_label' => 'ZZ Shelf mark', 'field_type' => 'text', 'entity_type' => 'informationobject',
            'is_active' => '1', 'include_in_export' => '0', 'is_visible_edit' => '1'];

        $this->actingAs($admin)->post(route('customFields.save'), $form)->assertRedirect(route('customFields.index'));
        $def = DB::table('custom_field_definition')->where('field_key', 'zz_shelf_mark')->first();
        $this->assertNotNull($def);
        $this->assertSame(0, (int) $def->include_in_export);

        $this->actingAs($admin)->post(route('customFields.save'), $form)->assertSessionHasErrors('field_key');
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'cf-admin-'.$id, 'email' => uniqid('cf-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
