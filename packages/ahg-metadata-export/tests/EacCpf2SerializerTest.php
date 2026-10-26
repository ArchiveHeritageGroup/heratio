<?php

/**
 * EacCpf2SerializerTest - heratio#1551: authority records export as EAC-CPF
 * 2.0 XML that validates against the published schema (vendored at
 * resources/schemas/eac-cpf-2.0.xsd), from the serializer, the authority
 * export page and the API.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\EacCpf2Serializer;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EacCpf2SerializerTest extends TestCase
{
    use DatabaseTransactions;

    private const XSD = __DIR__.'/../resources/schemas/eac-cpf-2.0.xsd';

    private int $person;

    private int $body;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('actor') || ! Schema::hasTable('relation')) {
            $this->markTestSkipped('actor tables not present in this install.');
        }
        $now = now();
        $make = function (string $class) use ($now) {
            return DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => $now, 'updated_at' => $now]);
        };

        // A person with every ISAAR field filled, and a corporate body.
        $this->person = $make('QubitActor');
        DB::table('actor')->insert(['id' => $this->person, 'entity_type_id' => 132, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $this->person, 'culture' => 'en',
            'authorized_form_of_name' => 'Test Person EAC', 'dates_of_existence' => '1890-1960',
            'history' => 'Born & raised <here>.', 'places' => 'Cape Town', 'legal_status' => 'Private person',
            'functions' => 'Surveying', 'mandates' => 'None recorded', 'internal_structures' => 'Family of five',
            'general_context' => 'Colonial survey office', 'sources' => 'Parish register']);
        DB::table('slug')->insert(['object_id' => $this->person, 'slug' => 'test-person-eac-'.$this->person]);
        $other = DB::table('other_name')->insertGetId(['object_id' => $this->person, 'source_culture' => 'en']);
        DB::table('other_name_i18n')->insert(['id' => $other, 'culture' => 'en', 'name' => 'T. P. Eac']);
        DB::table('ahg_actor_identifier')->insert(['actor_id' => $this->person, 'identifier_type' => 'wikidata',
            'identifier_value' => 'Q42', 'uri' => 'https://www.wikidata.org/wiki/Q42']);

        $this->body = $make('QubitActor');
        DB::table('actor')->insert(['id' => $this->body, 'entity_type_id' => 131, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $this->body, 'culture' => 'en', 'authorized_form_of_name' => 'Test Body EAC']);
        DB::table('slug')->insert(['object_id' => $this->body, 'slug' => 'test-body-eac-'.$this->body]);

        $rel = $make('QubitRelation');
        DB::table('relation')->insert(['id' => $rel, 'subject_id' => $this->person, 'object_id' => $this->body,
            'start_date' => '1920-01-01', 'end_date' => '1950-12-31', 'source_culture' => 'en']);
    }

    private function assertValidEac(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        libxml_use_internal_errors(true);
        $valid = $dom->schemaValidate(self::XSD);
        $errors = array_map(fn ($e) => 'line '.$e->line.': '.trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($valid, "EAC-CPF 2.0 schema errors:\n".implode("\n", $errors));

        return $dom;
    }

    public function test_a_full_record_validates_against_the_eac_cpf_2_schema(): void
    {
        $dom = $this->assertValidEac(app(EacCpf2Serializer::class)->serializeActor($this->person));
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('e', EacCpf2Serializer::NS_EAC);

        $this->assertSame('person', $xp->evaluate('string(//e:identity/e:entityType/@value)'));
        $this->assertSame('Test Person EAC', $xp->evaluate('string(//e:nameEntry[@status="authorized"]/e:part)'));
        $this->assertSame('T. P. Eac', $xp->evaluate('string(//e:nameEntry[@status="alternative"]/e:part)'));
        $this->assertSame('Born & raised <here>.', $xp->evaluate('string(//e:biogHist/e:p)'));
        $this->assertSame('Q42', $xp->evaluate('string(//e:control/e:otherRecordId[@localType="wikidata"])'));
        $this->assertSame('corporateBody', $xp->evaluate('string(//e:relation/e:targetEntity/@targetType)'));
        $this->assertSame('Test Body EAC', $xp->evaluate('string(//e:relation/e:targetEntity/e:part)'));
        $this->assertSame('1950-12-31', $xp->evaluate('string(//e:relation/e:dateRange/e:toDate/@standardDate)'));

        // The other side of the relation validates too.
        $this->assertValidEac(app(EacCpf2Serializer::class)->serializeActor($this->body));
    }

    private function signIn(int $groupId): void
    {
        $user = app(UserService::class)->create(['username' => 'eac'.bin2hex(random_bytes(4)), 'email' => 'eac'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'EAC test user', 'active' => 1, 'groups' => []]);
        DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => $groupId]);
        Cache::forget("acl_groups_{$user}");
        $this->actingAs(\AhgCore\Models\User::query()->findOrFail($user));
    }

    public function test_the_authority_export_page_downloads_eac_cpf_xml_not_csv(): void
    {
        $this->signIn(\AhgCore\Models\AclGroup::ADMINISTRATOR_ID);
        $response = $this->post('/export/authority', ['format' => 'eac', 'limit' => 0]);
        $response->assertOk();
        $this->assertStringContainsString('application/zip', $response->headers->get('Content-Type'));

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('test-person-eac-'.$this->person.'.xml');
        $this->assertNotFalse($xml, 'the person is in the archive under its slug');
        $this->assertValidEac($xml);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $userSlugs = DB::table('user')->join('slug', 'slug.object_id', '=', 'user.id')->limit(50)->pluck('slug.slug');
        foreach ($userSlugs as $slug) {
            $this->assertNotContains($slug.'.xml', $names, 'user accounts are not authority records');
        }

        $csv = $this->post('/export/authority', ['format' => 'csv', 'limit' => 0]);
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
    }

    public function test_the_api_returns_eac_cpf_xml(): void
    {
        $this->signIn(\AhgCore\Models\AclGroup::ADMINISTRATOR_ID);
        $response = $this->get('/api/authority/eac-export/'.$this->person);
        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertValidEac($response->getContent());
    }
}
