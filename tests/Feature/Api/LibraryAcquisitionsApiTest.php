<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Feature coverage for the library acquisitions JSON:API (heratio#1100).
 * Skips when the Phase-0 acquisitions tables are not provisioned in the test
 * database (they predate Laravel migrations); runs fully in a complete schema.
 */

namespace Tests\Feature\Api;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibraryAcquisitionsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['library_vendor', 'library_budget', 'library_order', 'library_order_line'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Acquisitions table {$table} not provisioned in the test DB.");
            }
        }

        $this->actingAs($this->makeUser(admin: true));
    }

    private function makeUser(bool $admin): User
    {
        $id = DB::table('users')->insertGetId([
            'name'     => $admin ? 'Acq API Admin' : 'Acq API Nobody',
            'email'    => uniqid('acq-api-', true) . '@example.test',
            'password' => Hash::make('secret'),
        ]);
        if ($admin) {
            // AclGroup::ADMINISTRATOR_ID = 100 -> AclService grants every action.
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function budgetId(string $code): int
    {
        return (int) DB::table('library_budget')->where('budget_code', $code)->value('id');
    }

    public function test_vendor_crud_lifecycle(): void
    {
        $create = $this->postJson('/api/library/vendors', [
            'vendor_code' => 'TST-V1', 'name' => 'Test Vendor', 'vendor_type' => 'local',
        ]);
        $create->assertStatus(201)
            ->assertJsonPath('data.type', 'library-vendors')
            ->assertJsonPath('data.attributes.name', 'Test Vendor');
        $id = $create->json('data.id');

        $this->getJson("/api/library/vendors/{$id}")->assertOk()->assertJsonPath('data.id', (string) $id);
        $this->patchJson("/api/library/vendors/{$id}", ['name' => 'Renamed Vendor'])
            ->assertOk()->assertJsonPath('data.attributes.name', 'Renamed Vendor');
        $this->getJson('/api/library/vendors')->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $this->deleteJson("/api/library/vendors/{$id}")->assertNoContent();
        $this->getJson("/api/library/vendors/{$id}")->assertNotFound();
    }

    public function test_budget_crud_lifecycle(): void
    {
        $create = $this->postJson('/api/library/budgets', [
            'budget_code' => 'TST-B1', 'fund_name' => 'Test Fund', 'fiscal_year' => '2026',
            'allocated_amount' => 5000,
        ]);
        $create->assertStatus(201)
            ->assertJsonPath('data.type', 'library-budgets')
            ->assertJsonPath('data.attributes.available_amount', 5000.0);
        $id = $create->json('data.id');

        $this->patchJson("/api/library/budgets/{$id}", ['allocated_amount' => 8000])
            ->assertOk()->assertJsonPath('data.attributes.available_amount', 8000.0);
        $this->deleteJson("/api/library/budgets/{$id}")->assertNoContent();
    }

    public function test_order_with_lines_and_budget_commitment(): void
    {
        $vendorId = $this->postJson('/api/library/vendors', ['vendor_code' => 'TST-OV', 'name' => 'Order Vendor'])
            ->json('data.id');
        $this->postJson('/api/library/budgets', [
            'budget_code' => 'TST-OB', 'fund_name' => 'Order Fund', 'fiscal_year' => '2026', 'allocated_amount' => 5000,
        ])->assertStatus(201);

        $order = $this->postJson('/api/library/orders', [
            'vendor_id'   => (int) $vendorId,
            'budget_code' => 'TST-OB',
            'status'      => 'ordered',
            'lines'       => [['title' => 'Line One', 'unit_price' => 100, 'quantity' => 2]],
        ]);
        $order->assertStatus(201)
            ->assertJsonPath('data.type', 'library-orders')
            ->assertJsonPath('data.attributes.total', 200.0)
            ->assertJsonPath('data.relationships.vendor.data.type', 'library-vendors');
        $orderId = $order->json('data.id');

        // Budget committed amount reflects the order.
        $this->getJson('/api/library/budgets/' . $this->budgetId('TST-OB'))
            ->assertOk()->assertJsonPath('data.attributes.committed_amount', 200.0);

        // Add a second line; total recalculates.
        $this->postJson("/api/library/orders/{$orderId}/lines", ['title' => 'Line Two', 'unit_price' => 50, 'quantity' => 1])
            ->assertStatus(201);
        $this->getJson("/api/library/orders/{$orderId}/lines")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/library/orders/{$orderId}")->assertOk()->assertJsonPath('data.attributes.total', 250.0);

        $this->deleteJson("/api/library/orders/{$orderId}")->assertNoContent();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(admin: false));
        $this->postJson('/api/library/vendors', ['vendor_code' => 'NO', 'name' => 'No'])
            ->assertForbidden();
    }
}
