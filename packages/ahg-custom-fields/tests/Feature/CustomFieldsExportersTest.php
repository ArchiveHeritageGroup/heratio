<?php

/**
 * CustomFieldsExportersTest - custom fields in the remaining exporters and on
 * other entities (heratio#1548).
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

use AhgCustomFields\Services\CustomFieldService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomFieldsExportersTest extends TestCase
{
    use DatabaseTransactions;

    private int $io;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('custom_field_definition')->update(['is_active' => 0]);
        $svc = app(CustomFieldService::class);
        $svc->createDefinition(['field_key' => 'grant_number', 'field_label' => 'ZZ Grant number', 'field_type' => 'text', 'entity_type' => 'informationobject']);
        $svc->createDefinition(['field_key' => 'donor_ref', 'field_label' => 'ZZ Donor reference', 'field_type' => 'text', 'entity_type' => 'actor']);
        $this->io = Io::create(['title' => 'ZZ exporters test'], 'en');
        \AhgCore\Support\StatusRow::set($this->io, 158, 160);
        $svc->saveValues($this->io, 'informationobject', ['grant_number' => 'NRF-77']);
    }

    public function test_the_metadata_export_serializers_carry_the_value(): void
    {
        $ns = '\\AhgMetadataExport\\Services\\Exporters\\';
        $this->assertStringContainsString('<odd type="grant_number"><head>ZZ Grant number</head><p>NRF-77</p></odd>', (new ($ns.'Ead2002Serializer'))->serializeRecord($this->io, 'en'));
        $this->assertStringContainsString('<odd localtype="grant_number">', (new ($ns.'Ead3Serializer'))->serializeRecord($this->io, 'en'));
        $this->assertStringContainsString('ZZ Grant number: NRF-77', (new ($ns.'DublinCoreQualifiedSerializer'))->serializeRecord($this->io, 'en'));
        $marc = (new ($ns.'MarcxmlSerializer'))->serializeRecord($this->io, 'en');
        $this->assertMatchesRegularExpression('/tag="500"[^>]*>\s*<subfield code="a">ZZ Grant number: NRF-77</', $marc);
        $this->assertStringContainsString('ZZ Grant number: NRF-77', (new ($ns.'MetsSerializer'))->serializeRecord($this->io, 'en'));
    }

    public function test_ric_json_ld_carries_a_descriptive_note(): void
    {
        $record = app(\AhgRic\Services\RicSerializationService::class)->serializeRecord($this->io);

        $this->assertContains('ZZ Grant number: NRF-77', (array) ($record['rico:descriptiveNote'] ?? []));
    }

    public function test_an_actor_saves_and_shows_its_own_fields(): void
    {
        $actor = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $actor, 'source_culture' => 'en']);
        $request = Request::create('/', 'POST', [CustomFieldService::FORM_MARKER => '1', 'cf' => ['donor_ref' => 'D-2026-9']]);

        CustomFieldService::saveFromRequest($actor, $request, 'actor');

        $html = view('ahg-custom-fields::partials._view-fields', ['entityType' => 'actor', 'objectId' => $actor])->render();
        $this->assertStringContainsString('D-2026-9', $html);
        $form = view('ahg-custom-fields::partials._edit-fields', ['entityType' => 'actor', 'objectId' => $actor])->render();
        $this->assertStringContainsString('name="cf[donor_ref]"', $form);
        $this->assertStringNotContainsString('cf[grant_number]', $form, 'description fields stay off the actor form');
    }
}
