<?php

/**
 * DescriptionRightsBlockTest - GET /api/v2/descriptions/{slug} carries a
 * `rights` block (rules and Local Contexts labels) in the shape PSIS sends,
 * for the AHG Archives MCP v0.9: rights in force today only, holders only for
 * staff keys, Local Contexts codes from the label's address or name, and
 * {"unavailable": true} - never an empty block - when the rights cannot be read.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Services\RightsBlockService;
use AhgCore\Services\DisclosureGate;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DescriptionRightsBlockTest extends TestCase
{
    use DatabaseTransactions;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['rights_record', 'rights_statement', 'rights_cc_license', 'icip_tk_label', 'icip_tk_label_type', 'object_rights_holder'] as $t) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not installed.");
            }
        }
        $now = now();
        $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Rights block test']);
        $this->slug = 'rights-block-'.$io;
        DB::table('slug')->insert(['object_id' => $io, 'slug' => $this->slug]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $io,
            'type_id' => DisclosureGate::STATUS_TYPE_PUBLICATION, 'status_id' => DisclosureGate::STATUS_PUBLISHED]);

        $statement = $this->idOrInsert('rights_statement', ['code' => 'InC'], ['uri' => 'http://rightsstatements.org/vocab/InC/1.0/', 'is_active' => 1]);
        $licence = $this->idOrInsert('rights_cc_license', ['code' => 'CC-BY-4.0'], ['version' => '4.0', 'uri' => 'https://creativecommons.org/licenses/by/4.0/']);

        // In force, with a holder; and one that ended yesterday.
        DB::table('rights_record')->insert(['object_id' => $io, 'basis' => 'copyright', 'rights_statement_id' => $statement, 'cc_license_id' => $licence,
            'copyright_holder' => 'A. Donor', 'rights_holder_uri' => 'https://example.org/donor', 'start_date' => '2020-01-01', 'end_date' => null]);
        DB::table('rights_record')->insert(['object_id' => $io, 'basis' => 'copyright', 'rights_statement_id' => $statement,
            'start_date' => '2020-01-01', 'end_date' => now()->subDay()->toDateString()]);

        // A Local Contexts label by its address, and a label the table does not know.
        $tks = $this->idOrInsert('icip_tk_label_type', ['code' => 'tk_s'], ['name' => 'TK Secret/Sacred', 'category' => 'TK',
            'local_contexts_url' => 'https://localcontexts.org/label/tk-secret-sacred/', 'is_local_contexts' => 1]);
        $local = DB::table('icip_tk_label_type')->insertGetId(['code' => 'zz_local', 'name' => 'Our own label', 'category' => 'TK', 'is_local_contexts' => 0]);
        foreach ([$tks, $local] as $type) {
            DB::table('icip_tk_label')->insert(['information_object_id' => $io, 'label_type_id' => $type]);
        }
    }

    private function idOrInsert(string $table, array $match, array $extra): int
    {
        return (int) (DB::table($table)->where($match)->value('id') ?: DB::table($table)->insertGetId($match + $extra));
    }

    private function key(?int $groupId): string
    {
        $user = app(UserService::class)->create(['username' => 'rb'.bin2hex(random_bytes(4)), 'email' => 'rb'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Rights reader', 'active' => 1, 'groups' => []]);
        if ($groupId) {
            DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => $groupId]);
        }
        Cache::forget("acl_groups_{$user}");

        return app(\AhgApi\Services\ApiKeyService::class)->issue($user, 'rights test', ['read'])['api_key'];
    }

    private function rights(string $key, string $query = ''): array
    {
        return $this->withHeaders(['X-API-Key' => $key])->getJson("/api/v2/descriptions/{$this->slug}{$query}")
            ->assertOk()->json('data.rights');
    }

    public function test_a_public_key_gets_rules_in_force_and_labels_without_holders(): void
    {
        $rights = $this->rights($this->key(null));

        $this->assertCount(1, $rights['rules'], 'the expired rule is left out');
        $rule = $rights['rules'][0];
        $this->assertSame('InC', $rule['statement']['code']);
        $this->assertSame('CC-BY-4.0', $rule['licence']['code']);
        $this->assertNull($rule['holder'], 'holders are for staff');
        $this->assertSame('2020-01-01', $rule['starts_on']);
        $this->assertSame([], $rule['grants']);

        $codes = array_column($rights['labels'], 'code', 'source_code');
        $this->assertSame('tk_s', $codes['tk_s']);
        $this->assertSame('https://localcontexts.org/label/tk-secret-sacred/', $rights['labels'][0]['iri']);
        $this->assertSame('local:zz_local', $codes['zz_local'], 'an unknown label is never taken for a Local Contexts one');
        $this->assertArrayNotHasKey('unavailable', $rights);
    }

    public function test_plain_reads_carry_the_block_too(): void
    {
        $this->assertCount(1, $this->rights($this->key(null), '?full=false')['rules']);
    }

    public function test_a_staff_key_also_gets_the_holder(): void
    {
        $rule = $this->rights($this->key(\AhgCore\Models\AclGroup::EDITOR_ID))['rules'][0];

        $this->assertSame(['name' => 'A. Donor', 'iri' => 'https://example.org/donor'], $rule['holder']);
    }

    public function test_rights_that_cannot_be_read_are_reported_unavailable_not_empty(): void
    {
        $this->app->instance(RightsBlockService::class, new class extends RightsBlockService {
            public function forObject(int $objectId, bool $staff, string $culture = 'en'): array
            {
                throw new \RuntimeException('rights tables unreadable');
            }
        });

        $this->assertSame(['rules' => [], 'labels' => [], 'unavailable' => true], $this->rights($this->key(null)));
    }

    public function test_grants_carry_the_premis_acts_from_both_rights_stores(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('rights_grant') || ! \Illuminate\Support\Facades\Schema::hasTable('granted_right')) {
            $this->markTestSkipped('rights_grant / granted_right not installed.');
        }
        $io = (int) DB::table('slug')->where('slug', $this->slug)->value('object_id');

        // Extended rights: a grant saved the way the rights form posts it (the reason as restriction_reason).
        $record = (int) DB::table('rights_record')->where('object_id', $io)->whereNull('end_date')->value('id');
        app(\AhgExtendedRights\Services\ExtendedRightsService::class)
            ->createGrant($record, ['act' => 'use', 'restriction' => 'conditional', 'restriction_reason' => 'Ask the holder first.']);

        // A PREMIS right linked by a RIGHT relation: Display disallowed, Disseminate conditional, and an expired Delete.
        $act = fn (string $name) => (int) DB::table('term as t')->join('term_i18n as i', 'i.id', '=', 't.id')
            ->where('t.taxonomy_id', 67)->where('i.culture', 'en')->where('i.name', $name)->value('t.id');
        if (! $act('Display') || ! $act('Disseminate') || ! $act('Delete')) {
            $this->markTestSkipped('The rights act terms are not seeded.');
        }
        $now = now();
        $rights = DB::table('object')->insertGetId(['class_name' => 'QubitRights', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('rights')->insert(['id' => $rights, 'source_culture' => 'en']);
        $rel = DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relation')->insert(['id' => $rel, 'subject_id' => $io, 'object_id' => $rights, 'type_id' => RightsBlockService::RIGHT_RELATION, 'source_culture' => 'en']);
        DB::table('granted_right')->insert([
            ['rights_id' => $rights, 'act_id' => $act('Display'), 'restriction' => 0, 'notes' => '', 'end_date' => null],
            ['rights_id' => $rights, 'act_id' => $act('Disseminate'), 'restriction' => 2, 'notes' => 'Credit the archive.', 'end_date' => null],
            ['rights_id' => $rights, 'act_id' => $act('Delete'), 'restriction' => 1, 'notes' => '', 'end_date' => now()->subDay()->toDateString()],
            // Display granted again, later, as allowed: the stricter disallow must still win.
            ['rights_id' => $rights, 'act_id' => $act('Display'), 'restriction' => 1, 'notes' => 'Later grant', 'end_date' => null],
        ]);

        $rules = $this->rights($this->key(null))['rules'];
        $this->assertCount(2, $rules);

        $this->assertSame(['use' => ['restriction' => 'conditional', 'note' => 'Ask the holder first.']], $rules[0]['grants']);
        $this->assertSame([
            'render' => ['restriction' => 'disallow', 'note' => null],
            'disseminate' => ['restriction' => 'conditional', 'note' => 'Credit the archive.'],
        ], $rules[1]['grants'], 'Display is published as render; the expired grant is left out');
    }

    public function test_label_and_licence_codes_follow_the_shared_rules(): void
    {
        // Address first, then the English name, never the stored code.
        $this->assertSame('tk_s', RightsBlockService::localContextsCode('https://localcontexts.org/label/tk-secret-sacred/', 'whatever', 'TK-SS'));
        $this->assertSame('tk_ss', RightsBlockService::localContextsCode(null, 'TK Seasonal', 'TK-SS'), 'TK-SS stored is not Seasonal by code');
        $this->assertSame('tk_cv', RightsBlockService::localContextsCode('https://example.org/wrong', 'TK Community Voice', 'TK-C'));
        $this->assertSame('bc_r', RightsBlockService::localContextsCode(null, 'BC Research Use', 'BC-R'));
        $this->assertSame('local:XX', RightsBlockService::localContextsCode(null, 'Not a label', 'XX'));

        $this->assertSame('CC-BY-4.0', RightsBlockService::licenceCode('CC-BY-4.0', '4.0'), 'version not added twice');
        $this->assertSame('CC-BY-NC-ND-4.0', RightsBlockService::licenceCode('by-nc-nd', '4.0'));
        $this->assertSame('CC0-1.0', RightsBlockService::licenceCode('CC0-1.0', '1.0'));
        $this->assertSame('CC0-1.0', RightsBlockService::licenceCode('cc0', '1.0'));
        $this->assertSame('PDM-1.0', RightsBlockService::licenceCode('PDM-1.0', '1.0'), 'not a CC licence: kept as is');
    }
}
