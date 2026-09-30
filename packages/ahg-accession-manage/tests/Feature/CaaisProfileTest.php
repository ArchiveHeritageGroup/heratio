<?php

/**
 * CaaisProfileTest - heratio#1514.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgAccessionManage\Services\AccessionService;
use AhgAccessionManage\Services\CaaisProfileService;
use AhgCore\Models\User;
use AhgCore\Services\PackageInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The CAAIS 1.0 profile over the core accession: side tables, dropdown-driven
 * validation, save semantics, the mandatory-element check and the export.
 */
class CaaisProfileTest extends TestCase
{
    use DatabaseTransactions;

    private const TABLES = [
        'accession_caais', 'accession_caais_source', 'accession_caais_extent', 'accession_caais_language',
        'accession_caais_preservation', 'accession_caais_event', 'accession_caais_revision',
    ];

    private AccessionService $core;

    private CaaisProfileService $caais;

    /**
     * Install the profile schema BEFORE DatabaseTransactions opens its
     * transaction: CREATE TABLE commits implicitly, which would otherwise end
     * the test's transaction and leave its rows behind.
     */
    protected function setUpTraits()
    {
        $root = dirname(__DIR__, 2);
        foreach (self::TABLES as $t) {
            if (! Schema::hasTable($t)) {
                PackageInstaller::autoInstall($root, true, $root.'/database/install_caais.sql');
                break;
            }
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Vocabulary rows are plain INSERT IGNORE, so they roll back with the test.
        DB::unprepared((string) file_get_contents(dirname(__DIR__, 2).'/database/seed_dropdowns.sql'));
        $this->core = new AccessionService('en');
        $this->caais = new CaaisProfileService('en');
    }

    private function accession(string $title = 'ZZ CAAIS test'): int
    {
        return $this->core->create(['identifier' => 'ZZ-CAAIS-'.Str::random(8), 'title' => $title]);
    }

    private function actor(string $class, string $name, ?int $parent = null): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => $class, 'created_at' => now(), 'updated_at' => now(), 'serial_number' => 0,
        ]);
        DB::table('actor')->insert(['id' => $id, 'parent_id' => $parent, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => Str::slug($name).'-'.Str::random(6)]);

        return $id;
    }

    private function repository(string $name): int
    {
        $id = $this->actor('QubitRepository', $name);
        DB::table('repository')->insert(['id' => $id, 'source_culture' => 'en']);

        return $id;
    }

    private function enableProfile(): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => CaaisProfileService::SETTING],
            ['setting_value' => '1', 'setting_group' => 'accession']
        );
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id,
            'username' => 'caais-admin-'.$id,
            'email' => uniqid('caais-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_install_creates_every_table_and_seeds_every_vocabulary_idempotently(): void
    {
        foreach (self::TABLES as $t) {
            $this->assertTrue(Schema::hasTable($t), "{$t} missing");
        }
        $choices = $this->caais->choices();
        foreach (array_keys(CaaisProfileService::TAXONOMIES) as $key) {
            $this->assertNotEmpty($choices[$key], "no options for {$key}");
        }
        $this->assertArrayHasKey(CaaisProfileService::EVENT_PHYSICAL, $choices['event_type']);
        $this->assertArrayHasKey(CaaisProfileService::EVENT_LEGAL, $choices['event_type']);

        $before = DB::table('ahg_dropdown')->where('taxonomy', 'like', 'caais\_%')->count();
        DB::unprepared((string) file_get_contents(dirname(__DIR__, 2).'/database/seed_dropdowns.sql'));
        $this->assertSame($before, DB::table('ahg_dropdown')->where('taxonomy', 'like', 'caais\_%')->count());
    }

    public function test_validation_accepts_dropdown_codes_and_rejects_anything_else(): void
    {
        $ok = Validator::make(['caais' => [
            '_profile' => '1',
            'extents' => [['extent_type' => 'extent_received', 'quantity' => '12', 'unit' => 'boxes', 'content_type' => 'textual']],
            'events' => [['event_type' => 'physical_transfer', 'event_date' => '2026-09-01']],
            'languages' => [['language' => 'fr']],
            'preservation' => [['requirement_type' => 'labour', 'requirement_value' => 'ca. 100 hours']],
        ]], $this->caais->rules());
        $this->assertFalse($ok->fails(), implode('; ', $ok->errors()->all()));

        $bad = Validator::make(['caais' => [
            'extents' => [['extent_type' => 'not_a_code', 'quantity' => '-1', 'unit' => 'boxes']],
            'events' => [['event_type' => 'physical_transfer', 'event_date' => 'not a date']],
            'languages' => [['language' => 'klingon']],
            'sources' => ['1' => 'shout_it_out'],
        ]], $this->caais->rules());
        $this->assertTrue($bad->fails());
        foreach (['caais.extents.0.extent_type', 'caais.extents.0.quantity', 'caais.events.0.event_date', 'caais.languages.0.language', 'caais.sources.1'] as $key) {
            $this->assertTrue($bad->errors()->has($key), "{$key} should fail");
        }
    }

    public function test_a_type_is_required_once_the_rest_of_a_row_is_filled(): void
    {
        $v = Validator::make(['caais' => [
            'extents' => [['extent_type' => '', 'quantity' => '3', 'unit' => '']],
            'preservation' => [['requirement_type' => '', 'requirement_value' => 'Needs a 16mm viewer']],
            'events' => [['event_type' => '', 'agent' => 'Someone']],
        ]], $this->caais->rules());
        $this->assertTrue($v->errors()->has('caais.extents.0.extent_type'));
        $this->assertTrue($v->errors()->has('caais.extents.0.unit'));
        $this->assertTrue($v->errors()->has('caais.preservation.0.requirement_type'));
        $this->assertTrue($v->errors()->has('caais.events.0.event_type'));

        // The form's empty template row passes.
        $blank = Validator::make(['caais' => ['extents' => [['extent_type' => '', 'quantity' => '', 'unit' => '', 'is_estimate' => '0']]]], $this->caais->rules());
        $this->assertFalse($blank->fails(), implode('; ', $blank->errors()->all()));
    }

    public function test_save_replaces_rows_skips_blank_rows_and_keeps_them_when_the_profile_is_off(): void
    {
        $id = $this->accession();
        $repo = $this->repository('ZZ CAAIS Repository');

        $this->caais->save($id, [
            '_profile' => '1',
            'repository_id' => $repo,
            'rules_or_conventions' => 'Canadian Archival Accession Information Standard 1.0',
            'extents' => [
                ['extent_type' => 'extent_received', 'quantity' => '5', 'is_estimate' => '1', 'unit' => 'linear_metres'],
                ['extent_type' => '', 'quantity' => '', 'unit' => '', 'is_estimate' => '0'],
            ],
            'events' => [['event_type' => 'physical_transfer', 'event_date' => '2026-09-01', 'agent' => 'J. Archivist']],
        ]);

        $p = $this->caais->get($id);
        $this->assertSame($repo, (int) $p['repository_id']);
        $this->assertSame('ZZ CAAIS Repository', $p['repository_name']);
        $this->assertCount(1, $p['extents']);
        $this->assertSame(1, (int) $p['extents'][0]['is_estimate']);
        $this->assertCount(1, $p['events']);

        // A second profile save replaces, not appends.
        $this->caais->save($id, ['_profile' => '1', 'repository_id' => $repo, 'events' => [
            ['event_type' => 'physical_transfer', 'event_date' => '2026-09-01'],
            ['event_type' => 'legal_transfer', 'event_date' => '2026-09-15'],
        ]]);
        $p = $this->caais->get($id);
        $this->assertCount(2, $p['events']);
        $this->assertSame([], $p['extents']);

        // Saved with the profile off: repository changes, recorded elements stay.
        $this->caais->save($id, ['repository_id' => null]);
        $p = $this->caais->get($id);
        $this->assertNull($p['repository_id']);
        $this->assertCount(2, $p['events']);
    }

    public function test_mandatory_check_and_export_follow_the_record(): void
    {
        $id = $this->accession('ZZ Papers of a CAAIS test');
        $donor = $this->actor('QubitDonor', 'ZZ Anonymous Donor', 3);
        $this->core->linkDonor($id, $donor);
        $this->caais->recordRevision($id, 'created');

        $record = $this->caais->exportRecord($id, $this->core);
        $missing = $record['conformance']['missing_mandatory'];
        $this->assertContains('5.1 - '.__('Event: physical transfer'), $missing);
        $this->assertContains('3.2 - '.__('Extent statement'), $missing);
        $this->assertNotContains('2.1 - '.__('Source of material'), $missing);
        $this->assertNotContains('7.2 - '.__('Date of creation (record created)'), $missing);

        $this->caais->save($id, [
            '_profile' => '1',
            'sources' => [$donor => 'anonymous'],
            'extents' => [['extent_type' => 'extent_received', 'quantity' => '12.500', 'unit' => 'boxes', 'carrier_type' => 'paper']],
            'languages' => [['language' => 'fr', 'note' => 'with partial English translation']],
            'preservation' => [['requirement_type' => 'technical_access', 'requirement_value' => 'Access to original film requires a 16mm viewer']],
            'events' => [['event_type' => 'physical_transfer', 'event_date' => '2026-09-01']],
        ]);

        $record = $this->caais->exportRecord($id, $this->core);
        $this->assertSame('ZZ Papers of a CAAIS test', $record['identity']['accession_title']);
        $this->assertSame('12.5 Boxes', $record['materials']['extent_statement'][0]['quantity_and_unit_of_measure']);
        $this->assertSame('Paper', $record['materials']['extent_statement'][0]['carrier_type']);
        $this->assertSame(['French - with partial English translation'], $record['materials']['language_of_material']);
        $this->assertSame('Technical access', $record['management']['preservation_requirements'][0]['preservation_requirement_type']);
        $this->assertSame('Physical transfer', $record['events'][0]['event_type']);
        $this->assertSame('Donor wishes to remain anonymous', $record['source']['source_of_material'][0]['source_confidentiality']);
        $this->assertNotContains('5.1 - '.__('Event: physical transfer'), $record['conformance']['missing_mandatory']);

        // The shareable export withholds the confidential source, but still
        // counts it for conformance.
        $external = $this->caais->exportRecord($id, $this->core, true);
        $this->assertSame([], $external['source']['source_of_material']);
        $this->assertNotContains('2.1 - '.__('Source of material'), $external['conformance']['missing_mandatory']);

        $env = $this->caais->envelope([$record]);
        $this->assertSame('1.0', $env['standard_version']);
        $this->assertSame('5.1', $env['crosswalk']['events']['element']);
    }

    public function test_free_text_core_fields_fall_back_when_nothing_is_structured(): void
    {
        $id = $this->core->create([
            'identifier' => 'ZZ-CAAIS-'.Str::random(8),
            'received_extent_units' => 'Three cartons',
            'physical_characteristics' => 'Some water damage',
        ]);
        $record = $this->caais->exportRecord($id, $this->core);
        $this->assertSame('Three cartons', $record['materials']['extent_statement'][0]['extent_note']);
        $this->assertSame('Some water damage', $record['management']['preservation_requirements'][0]['preservation_requirement_value']);
        $this->assertNotContains('3.2 - '.__('Extent statement'), $record['conformance']['missing_mandatory']);
    }

    public function test_edit_form_save_writes_profile_and_revision_and_show_renders_it(): void
    {
        $this->enableProfile();
        $admin = $this->makeAdmin();
        $id = $this->accession('ZZ Round trip');
        $slug = $this->core->getSlug($id);
        $identifier = DB::table('accession')->where('id', $id)->value('identifier');

        $this->actingAs($admin)->get(route('accession.edit', $slug))
            ->assertOk()
            ->assertSee('caais[extents][0][extent_type]', false)
            ->assertSee('caais[_profile]', false);

        $this->actingAs($admin)->post(route('accession.update', $slug), [
            'identifier' => $identifier,
            'caais' => [
                '_profile' => '1',
                'rules_or_conventions' => 'CAAIS 1.0',
                'extents' => [['extent_type' => 'extent_received', 'quantity' => '2', 'unit' => 'boxes']],
                'events' => [['event_type' => 'physical_transfer', 'event_date' => '2026-09-02']],
            ],
        ])->assertRedirect(route('accession.show', $slug));

        $p = $this->caais->get($id);
        $this->assertSame('CAAIS 1.0', $p['rules_or_conventions']);
        $this->assertCount(1, $p['extents']);
        $this->assertSame('revised', end($p['revisions'])['revision_type']);
        $this->assertSame($admin->username, end($p['revisions'])['agent']);

        $this->actingAs($admin)->get(route('accession.show', $slug))
            ->assertOk()
            ->assertSee('Repository and CAAIS profile')
            ->assertSee('Physical transfer');

        // An unknown code is refused and nothing is written.
        $this->actingAs($admin)->post(route('accession.update', $slug), [
            'identifier' => $identifier,
            'caais' => ['_profile' => '1', 'events' => [['event_type' => 'made_up']]],
        ])->assertSessionHasErrors('caais.events.0.event_type');
        $this->assertCount(1, $this->caais->get($id)['events']);
    }

    public function test_export_routes_are_admin_only_and_return_caais_json(): void
    {
        $id = $this->accession('ZZ Export me');

        $this->assertAdminGated($this->get(route('accession.caais-export', $id)));
        $this->assertAdminGated($this->get(route('accession.caais-export-all')));

        $admin = $this->makeAdmin();
        $res = $this->actingAs($admin)->get(route('accession.caais-export', $id));
        $res->assertOk()->assertJsonPath('standard_version', '1.0')
            ->assertJsonPath('records.0.identity.accession_title', 'ZZ Export me');

        $this->actingAs($admin)->get(route('accession.caais-export', 999999999))->assertNotFound();
    }
}
