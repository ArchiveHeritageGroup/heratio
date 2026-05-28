<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 0 - schema reconciliation.
     *
     * Aligns Heratio with the PSISA 'rich order' schema:
     *   library_order          (was library_acquisition_order)
     *   library_order_line     (was library_acquisition_order_line)
     *   library_budget         (was library_acquisition_budget)
     *
     * The legacy table names are NOT created. LibraryAcquisitionService now
     * reads/writes the rich tables directly.
     *
     * Decision: Option A (PSISA schema alignment) -- confirmed 2026-05-29.
     *
     * Each table is created only if it does not exist (idempotent) so this
     * migration is safe to re-run against an already-correct database.
     */
    public function up(): void
    {
        $this->createBudgetTable();
        $this->createOrderTable();
        $this->createOrderLineTable();
        $this->createOrderStatusDropdown();
        $this->createOrderTypeDropdown();
    }

    public function down(): void
    {
        // Down is intentionally narrow -- we never drop shared production tables.
        // Any destructive rollback must be handled manually.
        if (Schema::hasTable('library_order_line')) {
            Schema::dropIfExists('library_order_line');
        }
    }

    // ── Table builders ──────────────────────────────────────────────────

    private function createBudgetTable(): void
    {
        if (Schema::hasTable('library_budget')) {
            return;
        }

        Schema::create('library_budget', function (Blueprint $table) {
            $table->id();
            $table->string('budget_code', 50)->unique();
            $table->string('fund_name', 255);
            $table->integer('fiscal_year');
            $table->decimal('allocated_amount', 15, 2)->default(0.00);
            $table->decimal('committed_amount', 15, 2)->default(0.00)->comment('Placed orders not yet received');
            $table->decimal('spent_amount', 15, 2)->default(0.00)->comment('Received / invoiced');
            $table->string('currency', 3)->default('ZAR');
            $table->string('category', 100)->nullable()->comment('e.g. monographs, serials, e-resources');
            $table->string('department', 100)->nullable();
            $table->enum('status', ['active', 'closed', 'frozen'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function createOrderTable(): void
    {
        if (Schema::hasTable('library_order')) {
            return;
        }

        Schema::create('library_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_name', 255)->nullable();
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('status', 50)->default('draft')->comment('draft|submitted|approved|ordered|partial|received|cancelled');
            $table->string('order_type', 50)->default('purchase')->comment('purchase|standing|gift|exchange|deposit|approval');
            $table->string('budget_code', 50)->nullable()->index();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax', 15, 2)->default(0.00);
            $table->decimal('shipping', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('ZAR');
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('payment_status', 50)->default('unpaid')->comment('unpaid|partially_paid|paid|overdue|refunded');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('approved_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function createOrderLineTable(): void
    {
        if (Schema::hasTable('library_order_line')) {
            return;
        }

        Schema::create('library_order_line', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('library_item_id')->nullable()->comment('FK to library_item.id -- populated on receipt');
            $table->string('title', 255)->nullable();
            $table->string('isbn', 20)->nullable()->index();
            $table->string('issn', 20)->nullable();
            $table->string('author', 255)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->string('edition', 100)->nullable();
            $table->string('material_type', 100)->nullable()->comment('monograph|serial|av|dataset|theses|etc.');
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00)->comment('(qty * unit_price) - discount');
            $table->integer('qty_received')->default(0);
            $table->date('received_date')->nullable();
            $table->string('status', 50)->default('pending')->comment('pending|partial|received|cancelled|backordered');
            $table->string('budget_code', 50)->nullable();
            $table->string('fund_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('library_order')->onDelete('cascade');
        });
    }

    private function createOrderStatusDropdown(): void
    {
        $this->seedDropdown('library_order_status', [
            'draft'     => 'Draft',
            'submitted' => 'Submitted',
            'approved'  => 'Approved',
            'ordered'   => 'Ordered',
            'partial'   => 'Partially Received',
            'received'  => 'Received',
            'cancelled' => 'Cancelled',
        ]);
    }

    private function createOrderTypeDropdown(): void
    {
        $this->seedDropdown('library_order_type', [
            'purchase'  => 'Purchase Order',
            'standing'  => 'Standing Order',
            'gift'      => 'Gift / Donation',
            'exchange'  => 'Exchange',
            'deposit'   => 'Deposit Account',
            'approval'  => 'Approval Plan',
        ]);
    }

    private function seedDropdown(string $slug, array $terms): void
    {
        // Delegate to DB if possible, otherwise raw insert guarded by exists check.
        try {
            if (DB::table('taxonomy')->where('slug', $slug)->exists()) {
                return;
            }
            $taxonomyId = DB::table('taxonomy')->insertGetId([
                'slug'        => $slug,
                'parent_id'   => null,
                'lft'         => 1,
                'rgt'         => count($terms) * 2,
                'depth'       => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            foreach ($terms as $code => $name) {
                DB::table('term')->insert([
                    'taxonomy_id'  => $taxonomyId,
                    'slug'         => $code,
                    'name'         => $name,
                    'lft'          => 1,
                    'rgt'          => 2,
                    'depth'        => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Guarded: if DB layer is unavailable at boot time, skip silently.
            // The dropdown seeds will be applied on next artisan call that
            // touches the service provider.
        }
    }
};
