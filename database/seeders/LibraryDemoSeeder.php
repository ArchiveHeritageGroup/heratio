<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Demo data for the library acquisitions JSON:API (heratio#1100).
 * Idempotent: keyed on vendor_code / budget_code / order_number so repeated
 * `php artisan db:seed --class=LibraryDemoSeeder` runs are safe.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── Vendors ──────────────────────────────────────────────────────
        $vendors = [
            [
                'vendor_code' => 'VEND-LOCAL-01', 'name' => 'Protea Book Distributors',
                'vendor_type' => 'local', 'email' => 'orders@proteabooks.example',
                'city' => 'Cape Town', 'country' => 'South Africa', 'currency' => 'ZAR',
            ],
            [
                'vendor_code' => 'VEND-INTL-01', 'name' => 'Ingram Content Group',
                'vendor_type' => 'international', 'email' => 'eu-orders@ingram.example',
                'city' => 'La Vergne', 'country' => 'United States', 'currency' => 'USD',
            ],
        ];
        foreach ($vendors as $v) {
            DB::table('library_vendor')->updateOrInsert(
                ['vendor_code' => $v['vendor_code']],
                array_merge($v, ['is_active' => 1, 'updated_at' => $now, 'created_at' => $now]),
            );
        }
        $localVendorId = (int) DB::table('library_vendor')->where('vendor_code', 'VEND-LOCAL-01')->value('id');

        // ── Budgets ──────────────────────────────────────────────────────
        $budgets = [
            [
                'budget_code' => 'BUD-2026-MONO', 'fund_name' => 'Monographs 2026', 'fiscal_year' => '2026',
                'allocated_amount' => 500000.00, 'committed_amount' => 0.00, 'spent_amount' => 0.00,
                'currency' => 'ZAR', 'category' => 'monograph', 'department' => 'Technical Services', 'status' => 'active',
            ],
            [
                'budget_code' => 'BUD-2026-SER', 'fund_name' => 'Serials 2026', 'fiscal_year' => '2026',
                'allocated_amount' => 750000.00, 'committed_amount' => 0.00, 'spent_amount' => 0.00,
                'currency' => 'ZAR', 'category' => 'serial', 'department' => 'Technical Services', 'status' => 'active',
            ],
        ];
        foreach ($budgets as $b) {
            DB::table('library_budget')->updateOrInsert(
                ['budget_code' => $b['budget_code']],
                array_merge($b, ['updated_at' => $now, 'created_at' => $now]),
            );
        }

        // ── Order + lines ────────────────────────────────────────────────
        $orderNumber = 'DEMO-PO-0001';
        $existing = DB::table('library_order')->where('order_number', $orderNumber)->first();
        if (!$existing) {
            $orderId = (int) DB::table('library_order')->insertGetId([
                'order_number' => $orderNumber,
                'order_date'   => $now->toDateString(),
                'vendor_id'    => $localVendorId,
                'vendor_name'  => 'Protea Book Distributors',
                'budget_code'  => 'BUD-2026-MONO',
                'order_type'   => 'purchase',
                'status'       => 'ordered',
                'currency'     => 'ZAR',
                'payment_status' => 'unpaid',
                'subtotal'     => 0.0, 'tax' => 0.0, 'shipping' => 0.0, 'total' => 0.0,
                'notes'        => 'Demo order seeded by LibraryDemoSeeder (#1100).',
                'created_at'   => $now, 'updated_at' => $now,
            ]);

            $lines = [
                ['title' => 'Introduction to Information Science', 'isbn' => '9781783302659', 'author' => 'David Bawden', 'unit_price' => 1250.00],
                ['title' => 'The Discipline of Organizing', 'isbn' => '9780262518505', 'author' => 'Robert J. Glushko', 'unit_price' => 980.00],
            ];
            $subtotal = 0.0;
            foreach ($lines as $l) {
                DB::table('library_order_line')->insert([
                    'order_id'    => $orderId,
                    'title'       => $l['title'], 'isbn' => $l['isbn'], 'author' => $l['author'],
                    'quantity'    => 1, 'unit_price' => $l['unit_price'], 'discount_percent' => 0,
                    'line_total'  => $l['unit_price'], 'quantity_received' => 0,
                    'status'      => 'pending', 'budget_code' => 'BUD-2026-MONO',
                    'created_at'  => $now,
                ]);
                $subtotal += $l['unit_price'];
            }
            DB::table('library_order')->where('id', $orderId)->update([
                'subtotal' => $subtotal, 'total' => $subtotal, 'updated_at' => $now,
            ]);
            // Reflect the commitment on the budget.
            DB::table('library_budget')->where('budget_code', 'BUD-2026-MONO')
                ->update(['committed_amount' => $subtotal, 'updated_at' => $now]);
        }

        $this->command?->info('LibraryDemoSeeder: vendors, budgets and a demo order ready.');
    }
}
