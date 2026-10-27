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
