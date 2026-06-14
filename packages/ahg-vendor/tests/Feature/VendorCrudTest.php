<?php

/**
 * VendorCrudTest - #1265 behavioural Feature coverage for ahg-vendor.
 *
 * Exercises the controller's real CRUD / transaction behaviour through the
 * live admin routes (admin/vendor/...), acting as an administrator. The
 * write helpers themselves (encrypt-on-write + audit) live in VendorService
 * and are covered by VendorEncryptionAuditTest - this file does NOT duplicate
 * that; it covers list-filtering, validation, slug/code generation, service
 * sync, contacts, transactions, status history, items, and the stats shape.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase - the ~995 base tables must
 * survive; #1136).
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorCrudTest extends TestCase
{
    use DatabaseTransactions;

    /** Administrator group id (AclGroup::ADMINISTRATOR_ID) - bypasses all ACL. */
    private const ADMIN_GROUP = 100;

    private int $adminId;

    private int $serviceTypeId;

    private int $serviceTypeIdB;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable PII encryption so list-filter LIKE assertions on name/code/city
        // are deterministic and we can read columns back without a key. (The
        // encrypt-on path is covered by VendorEncryptionAuditTest.)
        \AhgCore\Services\AhgSettingsService::set('encryption_enabled', '0', 'encryption');
        \AhgCore\Services\AhgSettingsService::clearCache();

        $this->adminId = $this->resolveAdminUser();

        // Two known service types for service-sync + service_type_id filter.
        $this->serviceTypeId = $this->ensureServiceType('TestSvcA '.Str::random(4));
        $this->serviceTypeIdB = $this->ensureServiceType('TestSvcB '.Str::random(4));
    }

    /**
     * Resolve (or fabricate) an administrator user id, then actingAs it.
     * Prefers an existing group-100 user in heratio_test; if none exists,
     * maps a synthetic id into group 100 (rolled back with the transaction).
     */
    private function resolveAdminUser(): int
    {
        $id = (int) (DB::table('acl_user_group')
            ->where('group_id', self::ADMIN_GROUP)
            ->value('user_id') ?? 0);

        if ($id === 0) {
            // No seeded admin - fabricate the ACL membership for a synthetic id.
            // created_by / requested_by have no FK to `user`, so an in-memory
            // model with this id is sufficient for the controller writes.
            $id = 999_000_001;
            DB::table('acl_user_group')->insert([
                'user_id' => $id,
                'group_id' => self::ADMIN_GROUP,
            ]);
        }

        Cache::forget("acl_groups_{$id}");

        // Prefer a fully hydrated model (so theme partials that call
        // $user->isEditor()/isAdministrator() during view render resolve);
        // fall back to a bare model carrying just the id for the fabricated case.
        $user = User::find($id);
        if (! $user) {
            $user = new User;
            $user->id = $id;
        }
        $this->actingAs($user);

        return $id;
    }

    private function ensureServiceType(string $name): int
    {
        return (int) DB::table('ahg_vendor_service_types')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => 1,
            'display_order' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * POST the vendor add form and follow to the resulting vendor row.
     * Returns the created vendor row (stdClass) or null on validation failure.
     */
    private function postVendor(array $overrides = []): ?object
    {
        $name = $overrides['name'] ?? ('Acme Bindery '.Str::random(6));

        $payload = array_merge([
            'name' => $name,
            'vendor_type' => 'company',
            'city' => 'Cape Town',
            'email' => 'acme@example.test',
            'status' => 'active',
        ], $overrides);

        $this->post(route('ahgvendor.add'), $payload);

        return DB::table('ahg_vendors')->where('name', $payload['name'])->first();
    }

    // =====================================================================
    //  list() filters
    // =====================================================================

    public function test_list_filters_by_status_type_service_and_search(): void
    {
        $alpha = $this->postVendor([
            'name' => 'Alpha Conservation '.Str::random(5),
            'vendor_type' => 'company',
            'status' => 'active',
            'city' => 'Durban',
            'service_ids' => [$this->serviceTypeId],
        ]);
        $beta = $this->postVendor([
            'name' => 'Beta Supplies '.Str::random(5),
            'vendor_type' => 'individual',
            'status' => 'inactive',
            'city' => 'Pretoria',
            'service_ids' => [$this->serviceTypeIdB],
        ]);
        $this->assertNotNull($alpha);
        $this->assertNotNull($beta);

        // status filter
        $this->assertListShows(['status' => 'inactive'], $beta->name, $alpha->name);

        // vendor_type filter
        $this->assertListShows(['vendor_type' => 'individual'], $beta->name, $alpha->name);

        // service_type_id filter (whereExists on ahg_vendor_services)
        $this->assertListShows(['service_type_id' => $this->serviceTypeId], $alpha->name, $beta->name);

        // search LIKE on name
        $this->assertListShows(['search' => 'Alpha Conservation'], $alpha->name, $beta->name);

        // search LIKE on city
        $this->assertListShows(['search' => 'Durban'], $alpha->name, $beta->name);

        // search LIKE on vendor_code (auto-generated VNDyyNNNN)
        $this->assertListShows(['search' => $alpha->vendor_code], $alpha->name, $beta->name);
    }

    /**
     * Assert the filtered list shows $present and omits $absent.
     */
    private function assertListShows(array $filters, string $present, string $absent): void
    {
        [$html] = $this->listVendorNames($filters);
        $this->assertStringContainsString($present, $html, "expected '$present' in filtered list");
        $this->assertStringNotContainsString($absent, $html, "did not expect '$absent' in filtered list");
    }

    public function test_list_insurance_gate_excludes_expired_and_uninsured(): void
    {
        $insured = $this->postVendor([
            'name' => 'Insured Co '.Str::random(5),
            'has_insurance' => 1,
            'insurance_expiry_date' => date('Y-m-d', strtotime('+1 year')),
        ]);
        $expired = $this->postVendor([
            'name' => 'Expired Co '.Str::random(5),
            'has_insurance' => 1,
            'insurance_expiry_date' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $none = $this->postVendor(['name' => 'NoInsurance Co '.Str::random(5)]);

        [$html] = $this->listVendorNames(['has_insurance' => 1]);
        $this->assertStringContainsString($insured->name, $html);
        $this->assertStringNotContainsString($expired->name, $html, 'expired insurance must be gated out');
        $this->assertStringNotContainsString($none->name, $html, 'uninsured must be gated out');
    }

    /**
     * Run the list route with filters and return the vendor names rendered.
     * The themed layout is re-rendered to a string by response-injection
     * middleware (so TestResponse::viewData is unavailable here); we therefore
     * assert against the rendered HTML body, which still reflects exactly the
     * controller's filtered query result.
     */
    private function listVendorNames(array $filters): array
    {
        $resp = $this->get(route('ahgvendor.list', $filters));
        $resp->assertOk();

        return [$resp->getContent()];
    }

    // =====================================================================
    //  add() - validation + generation + service sync
    // =====================================================================

    public function test_add_rejects_missing_name_and_invalid_email(): void
    {
        $resp = $this->post(route('ahgvendor.add'), [
            'name' => '',
            'email' => 'not-an-email',
        ]);
        $resp->assertOk(); // re-renders the form (no redirect) with validation errors
        $resp->assertSee('Vendor name is required');
        $resp->assertSee('Invalid email address');

        // validation rejected the write
        $this->assertDatabaseMissing('ahg_vendors', ['email' => 'not-an-email']);
    }

    public function test_add_creates_row_with_generated_code_and_synced_services(): void
    {
        $name = 'Generated Vendor '.Str::random(6);
        $resp = $this->post(route('ahgvendor.add'), [
            'name' => $name,
            'vendor_type' => 'company',
            'status' => 'active',
            'service_ids' => [$this->serviceTypeId, $this->serviceTypeIdB],
        ]);

        $vendor = DB::table('ahg_vendors')->where('name', $name)->first();
        $this->assertNotNull($vendor);
        $resp->assertRedirect(route('ahgvendor.view', ['slug' => $vendor->slug]));

        // generated vendor_code: VND + 2-digit year + 4 digits
        $this->assertMatchesRegularExpression('/^VND\d{2}\d{4}$/', $vendor->vendor_code);
        // generated slug from name
        $this->assertNotEmpty($vendor->slug);

        // syncVendorServices wrote both rows
        $svcCount = DB::table('ahg_vendor_services')->where('vendor_id', $vendor->id)->count();
        $this->assertEquals(2, $svcCount);
    }

    public function test_add_generates_collision_suffixed_slug(): void
    {
        // Two vendors with the same name -> second slug must be suffixed.
        // (Reading back by name is ambiguous once two rows share it, so fetch
        // both rows explicitly and compare their slugs.)
        $name = 'Collision Vendor '.Str::random(4);
        $this->post(route('ahgvendor.add'), ['name' => $name, 'vendor_type' => 'company', 'status' => 'active']);
        $this->post(route('ahgvendor.add'), ['name' => $name, 'vendor_type' => 'company', 'status' => 'active']);

        $rows = DB::table('ahg_vendors')->where('name', $name)->orderBy('id')->get();
        $this->assertCount(2, $rows, 'both vendors should be created');
        $slugs = $rows->pluck('slug')->all();
        $this->assertNotEquals($slugs[0], $slugs[1], 'duplicate name must get a unique slug');
        $this->assertStringStartsWith($slugs[0], $slugs[1], 'collision slug should be the base plus a suffix');
    }

    // =====================================================================
    //  edit() / delete()
    // =====================================================================

    public function test_edit_round_trips_update_and_reslugs_on_rename(): void
    {
        $vendor = $this->postVendor(['name' => 'EditMe '.Str::random(5)]);
        $newName = 'Renamed '.Str::random(5);

        $resp = $this->post(route('ahgvendor.edit', ['slug' => $vendor->slug]), [
            'name' => $newName,
            'vendor_type' => 'company',
            'status' => 'inactive',
            'notes' => 'updated via test',
        ]);

        $after = DB::table('ahg_vendors')->where('id', $vendor->id)->first();
        $resp->assertRedirect(route('ahgvendor.view', ['slug' => $after->slug]));
        $this->assertEquals($newName, $after->name);
        $this->assertEquals('inactive', $after->status);
        $this->assertEquals('updated via test', $after->notes);
        // slug regenerated to match the new name, still unique
        $this->assertNotEquals($vendor->slug, $after->slug);
    }

    public function test_delete_blocked_when_active_transaction_exists(): void
    {
        $vendor = $this->postVendor(['name' => 'Undeletable '.Str::random(5)]);

        // Active (non-returned/cancelled) transaction blocks delete.
        DB::table('ahg_vendor_transactions')->insert([
            'transaction_number' => 'TXN-TEST-'.Str::random(5),
            'vendor_id' => $vendor->id,
            'service_type_id' => $this->serviceTypeId,
            'status' => 'in_progress',
            'request_date' => date('Y-m-d'),
            'requested_by' => $this->adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->post(route('ahgvendor.delete', ['slug' => $vendor->slug]));
        $resp->assertRedirect(route('ahgvendor.list'));
        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('ahg_vendors', ['id' => $vendor->id]);
    }

    public function test_delete_succeeds_when_no_active_transactions(): void
    {
        $vendor = $this->postVendor(['name' => 'Deletable '.Str::random(5)]);

        $resp = $this->post(route('ahgvendor.delete', ['slug' => $vendor->slug]));
        $resp->assertRedirect(route('ahgvendor.list'));
        $resp->assertSessionHas('notice');
        $this->assertDatabaseMissing('ahg_vendors', ['id' => $vendor->id]);
    }

    // =====================================================================
    //  contacts
    // =====================================================================

    public function test_contact_add_update_delete_round_trip(): void
    {
        $vendor = $this->postVendor(['name' => 'Contact Vendor '.Str::random(5)]);

        // add
        $this->post(route('ahgvendor.add-contact', ['slug' => $vendor->slug]), [
            'contact_name' => 'Jane Doe',
            'position' => 'Manager',
            'contact_email' => 'jane@example.test',
            'is_primary' => 1,
        ])->assertRedirect(route('ahgvendor.view', ['slug' => $vendor->slug]));

        $contact = DB::table('ahg_vendor_contacts')->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Jane Doe', $contact->name);
        $this->assertEquals(1, (int) $contact->is_primary);

        // update
        $this->post(route('ahgvendor.update-contact', ['slug' => $vendor->slug, 'contactId' => $contact->id]), [
            'contact_name' => 'Jane Smith',
            'position' => 'Director',
        ])->assertRedirect(route('ahgvendor.view', ['slug' => $vendor->slug]));

        $updated = DB::table('ahg_vendor_contacts')->where('id', $contact->id)->first();
        $this->assertEquals('Jane Smith', $updated->name);
        $this->assertEquals('Director', $updated->position);

        // delete
        $this->post(route('ahgvendor.delete-contact', ['slug' => $vendor->slug, 'contactId' => $contact->id]))
            ->assertRedirect(route('ahgvendor.view', ['slug' => $vendor->slug]));
        $this->assertDatabaseMissing('ahg_vendor_contacts', ['id' => $contact->id]);
    }

    public function test_adding_second_primary_contact_demotes_the_first(): void
    {
        $vendor = $this->postVendor(['name' => 'Primary Vendor '.Str::random(5)]);

        $this->post(route('ahgvendor.add-contact', ['slug' => $vendor->slug]), [
            'contact_name' => 'First Primary',
            'is_primary' => 1,
        ]);
        $this->post(route('ahgvendor.add-contact', ['slug' => $vendor->slug]), [
            'contact_name' => 'Second Primary',
            'is_primary' => 1,
        ]);

        $primaries = DB::table('ahg_vendor_contacts')
            ->where('vendor_id', $vendor->id)
            ->where('is_primary', 1)
            ->count();
        $this->assertEquals(1, $primaries, 'only the latest primary should remain flagged');
    }

    // =====================================================================
    //  transactions
    // =====================================================================

    public function test_add_transaction_generates_number_and_initial_history(): void
    {
        $vendor = $this->postVendor(['name' => 'Txn Vendor '.Str::random(5)]);

        $resp = $this->post(route('ahgvendor.add-transaction'), [
            'vendor_id' => $vendor->id,
            'service_type_id' => $this->serviceTypeId,
            'request_date' => date('Y-m-d'),
        ]);

        $txn = DB::table('ahg_vendor_transactions')->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($txn);
        $resp->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));

        // generated number: TXN-YYYYMM-NNNN
        $this->assertMatchesRegularExpression('/^TXN-\d{6}-\d{4}$/', $txn->transaction_number);
        $this->assertEquals('pending_approval', $txn->status);

        // initial history row (status_from null -> created status)
        $hist = DB::table('ahg_vendor_transaction_history')->where('transaction_id', $txn->id)->first();
        $this->assertNotNull($hist);
        $this->assertNull($hist->status_from);
        $this->assertEquals('pending_approval', $hist->status_to);
    }

    public function test_add_transaction_rejects_missing_required_fields(): void
    {
        $countBefore = DB::table('ahg_vendor_transactions')->count();

        $resp = $this->post(route('ahgvendor.add-transaction'), [
            'vendor_id' => '',
            'service_type_id' => '',
            'request_date' => '',
        ]);
        $resp->assertOk();
        $resp->assertSee('Vendor is required');
        $resp->assertSee('Service type is required');
        $resp->assertSee('Request date is required');

        // nothing was inserted
        $this->assertEquals($countBefore, DB::table('ahg_vendor_transactions')->count());
    }

    public function test_update_transaction_status_writes_history_row(): void
    {
        [$vendor, $txn] = $this->makeTransaction();

        $resp = $this->post(route('ahgvendor.update-transaction-status', ['id' => $txn->id]), [
            'status' => 'approved',
            'notes' => 'approved by test',
        ]);
        $resp->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));

        $after = DB::table('ahg_vendor_transactions')->where('id', $txn->id)->first();
        $this->assertEquals('approved', $after->status);
        // status side-effects applied
        $this->assertEquals(date('Y-m-d'), substr((string) $after->approval_date, 0, 10));

        // history captures status_from -> status_to via logTransactionStatusChange
        $hist = DB::table('ahg_vendor_transaction_history')
            ->where('transaction_id', $txn->id)
            ->where('status_to', 'approved')
            ->first();
        $this->assertNotNull($hist);
        $this->assertEquals($txn->status, $hist->status_from);
        $this->assertEquals('approved', $hist->status_to);
        $this->assertEquals('approved by test', $hist->notes);
    }

    public function test_update_transaction_status_rejects_invalid_status(): void
    {
        [, $txn] = $this->makeTransaction();

        $resp = $this->post(route('ahgvendor.update-transaction-status', ['id' => $txn->id]), [
            'status' => 'not_a_real_status',
        ]);
        $resp->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));
        $resp->assertSessionHas('error');

        $after = DB::table('ahg_vendor_transactions')->where('id', $txn->id)->first();
        $this->assertEquals($txn->status, $after->status, 'status must be unchanged on invalid input');
    }

    public function test_transaction_item_add_update_remove(): void
    {
        [, $txn] = $this->makeTransaction();
        $ioId = $this->anyInformationObjectId();

        // add
        $this->post(route('ahgvendor.add-transaction-item', ['transactionId' => $txn->id]), [
            'information_object_id' => $ioId,
            'condition_before' => 'good',
            'declared_value' => '1500.00',
        ])->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));

        $item = DB::table('ahg_vendor_transaction_items')->where('transaction_id', $txn->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals($ioId, (int) $item->information_object_id);

        // update
        $this->post(route('ahgvendor.update-transaction-item', ['transactionId' => $txn->id, 'itemId' => $item->id]), [
            'condition_after' => 'excellent',
            'service_completed' => 1,
            'item_cost' => '900.00',
        ])->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));

        $updated = DB::table('ahg_vendor_transaction_items')->where('id', $item->id)->first();
        $this->assertEquals('excellent', $updated->condition_after);
        $this->assertEquals(1, (int) $updated->service_completed);

        // remove
        $this->post(route('ahgvendor.remove-transaction-item', ['transactionId' => $txn->id, 'itemId' => $item->id]))
            ->assertRedirect(route('ahgvendor.view-transaction', ['id' => $txn->id]));
        $this->assertDatabaseMissing('ahg_vendor_transaction_items', ['id' => $item->id]);
    }

    /**
     * Create a vendor + a transaction in a known status, return [$vendor,$txn].
     */
    private function makeTransaction(string $status = 'in_progress'): array
    {
        $vendor = $this->postVendor(['name' => 'Stat Vendor '.Str::random(5)]);
        $txnId = DB::table('ahg_vendor_transactions')->insertGetId([
            'transaction_number' => 'TXN-SEED-'.Str::random(6),
            'vendor_id' => $vendor->id,
            'service_type_id' => $this->serviceTypeId,
            'status' => $status,
            'request_date' => date('Y-m-d'),
            'requested_by' => $this->adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $txn = DB::table('ahg_vendor_transactions')->where('id', $txnId)->first();

        return [$vendor, $txn];
    }

    private function anyInformationObjectId(): int
    {
        $id = (int) (DB::table('information_object')->where('id', '>', 0)->value('id') ?? 0);
        if ($id === 0) {
            $this->markTestSkipped('no information_object rows in heratio_test for item linkage');
        }

        return $id;
    }

    // =====================================================================
    //  stats shape
    // =====================================================================

    /**
     * Invoke a private controller method (the stats builders are private; the
     * issue asks for their return shape, not their view wiring).
     */
    private function callPrivate(string $method, array $args = [])
    {
        $controller = new \AhgVendor\Controllers\VendorController;
        $ref = new \ReflectionMethod($controller, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($controller, $args);
    }

    public function test_dashboard_stats_shape(): void
    {
        // The dashboard route renders (smoke), and getDashboardStats returns
        // the documented shape.
        $this->get(route('ahgvendor.index'))->assertOk();

        $stats = $this->callPrivate('getDashboardStats');
        $this->assertIsArray($stats);
        foreach (['active_vendors', 'active_transactions', 'overdue_items', 'pending_approval', 'items_out', 'this_month_cost'] as $key) {
            $this->assertArrayHasKey($key, $stats, "dashboard stats missing $key");
        }
    }

    public function test_vendor_stats_shape(): void
    {
        [$vendor] = $this->makeTransaction();

        // View page renders (smoke).
        $this->get(route('ahgvendor.view', ['slug' => $vendor->slug]))->assertOk();

        $stats = $this->callPrivate('getVendorStats', [$vendor->id]);
        $this->assertIsObject($stats);
        $this->assertObjectHasProperty('total_transactions', $stats);
        $this->assertObjectHasProperty('active_transactions', $stats);
        $this->assertObjectHasProperty('items_handled', $stats);
        $this->assertGreaterThanOrEqual(1, (int) $stats->total_transactions);
    }
}
