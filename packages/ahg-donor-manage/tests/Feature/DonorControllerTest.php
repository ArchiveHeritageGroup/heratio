<?php

/**
 * DonorControllerTest - #1259 HTTP-level coverage for the donor + agreement
 * controller flows.
 *
 * Acts as an existing administrator user from heratio_test (resolved via
 * acl_user_group membership of the administrator group, 100) so the auth +
 * acl:create / admin / acl:delete route middleware are satisfied. If no admin
 * user exists the auth-gated cases are skipped.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase, per #1136).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\AclGroup;
use AhgCore\Models\User;
use AhgDonorManage\Services\DonorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorControllerTest extends TestCase
{
    use DatabaseTransactions;

    private ?User $admin = null;

    private DonorService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DonorService('en');

        $adminId = DB::table('acl_user_group')
            ->where('group_id', AclGroup::ADMINISTRATOR_ID)
            ->value('user_id');
        $this->admin = $adminId ? User::find($adminId) : null;
    }

    private function requireAdmin(): void
    {
        if (! $this->admin) {
            $this->markTestSkipped('No administrator user in heratio_test to act as.');
        }
    }

    private function uniqueName(string $prefix): string
    {
        return $prefix.' '.Str::random(8);
    }

    public function test_store_creates_donor_and_redirects_to_show(): void
    {
        $this->requireAdmin();

        $name = $this->uniqueName('Stored Donor');
        $resp = $this->actingAs($this->admin)->post(route('donor.store'), [
            'authorized_form_of_name' => $name,
        ]);

        $slug = Str::slug($name);
        $resp->assertredirect(route('donor.show', $slug));

        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        $this->assertNotNull($objectId);
        $this->assertEquals('QubitDonor', DB::table('object')->where('id', $objectId)->value('class_name'));
    }

    public function test_store_requires_authorized_form_of_name(): void
    {
        $this->requireAdmin();

        $resp = $this->actingAs($this->admin)
            ->from(route('donor.create'))
            ->post(route('donor.store'), []);

        $resp->assertSessionHasErrors('authorized_form_of_name');
    }

    public function test_update_keeps_slug(): void
    {
        $this->requireAdmin();

        $id = $this->svc->create(['authorized_form_of_name' => $this->uniqueName('Editable Donor')]);
        $slug = $this->svc->getSlug($id);

        $resp = $this->actingAs($this->admin)->post(route('donor.update', $slug), [
            'authorized_form_of_name' => $this->uniqueName('Edited Donor'),
        ]);

        $resp->assertRedirect(route('donor.show', $slug));
        // slug is stable across the rename
        $this->assertEquals($slug, $this->svc->getSlug($id));
    }

    public function test_destroy_deletes_and_redirects_to_browse(): void
    {
        $this->requireAdmin();

        $id = $this->svc->create(['authorized_form_of_name' => $this->uniqueName('Doomed Donor')]);
        $slug = $this->svc->getSlug($id);

        $resp = $this->actingAs($this->admin)->delete(route('donor.destroy', $slug));

        $resp->assertRedirect(route('donor.browse'));
        $this->assertFalse(DB::table('donor')->where('id', $id)->exists());
    }

    public function test_show_unknown_slug_returns_404(): void
    {
        $this->get(route('donor.show', 'no-such-donor-'.Str::random(8)))
            ->assertStatus(404);
    }

    public function test_agreement_add_inserts_donor_agreement(): void
    {
        $this->requireAdmin();

        $typeId = DB::table('agreement_type')->value('id');
        if (! $typeId) {
            $this->markTestSkipped('No agreement_type rows in heratio_test.');
        }

        $title = $this->uniqueName('Deed of Gift');
        $agreementNumber = 'AGR-'.Str::upper(Str::random(10));

        $resp = $this->actingAs($this->admin)->post(route('donor.agreement.add'), [
            'title' => $title,
            'agreement_number' => $agreementNumber,
            'agreement_type_id' => $typeId,
            'status' => 'draft',
        ]);

        $row = DB::table('donor_agreement')->where('agreement_number', $agreementNumber)->first();
        $this->assertNotNull($row, 'agreement row should be inserted');
        $this->assertEquals($title, $row->title);
        $this->assertEquals('draft', $row->status);
        $resp->assertRedirect(route('donor.agreement.view', $row->id));
    }

    public function test_agreement_autocompletes_return_json_empty_for_short_query(): void
    {
        $this->requireAdmin();

        $acc = $this->actingAs($this->admin)
            ->getJson(route('donor.agreement.autocomplete-accessions', ['query' => 'a']));
        $acc->assertStatus(200)->assertExactJson([]);

        $rec = $this->actingAs($this->admin)
            ->getJson(route('donor.agreement.autocomplete-records', ['query' => 'a']));
        $rec->assertStatus(200)->assertExactJson([]);
    }
}
