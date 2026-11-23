<?php

/**
 * Fine payment, receipts, waiver log and cash-desk sessions (#1474 item 1).
 * Additive only. library_fine itself is unchanged: its existing payment and
 * waiver columns hold the latest state, and these tables hold the history that
 * a single row cannot (several part-payments, drawer totals).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_cash_session')) {
            Schema::create('library_cash_session', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('branch_id')->nullable()->index('idx_cash_session_branch');
                $t->string('currency', 3);
                $t->string('status', 20)->default('open')->index('idx_cash_session_status');
                $t->string('open_key', 40)->nullable()->unique('uq_cash_session_open');
                $t->decimal('opening_float', 10, 2)->default(0);
                $t->integer('opened_by');
                $t->dateTime('opened_at')->index('idx_cash_session_opened');
                $t->decimal('cash_takings', 10, 2)->nullable();
                $t->decimal('other_takings', 10, 2)->nullable();
                $t->decimal('expected_cash', 10, 2)->nullable();
                $t->decimal('counted_cash', 10, 2)->nullable();
                $t->decimal('variance', 10, 2)->nullable();
                $t->text('close_notes')->nullable();
                $t->integer('closed_by')->nullable();
                $t->dateTime('closed_at')->nullable();
                $t->integer('reconciled_by')->nullable();
                $t->dateTime('reconciled_at')->nullable();
                $t->text('reconcile_notes')->nullable();
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->timestamp('updated_at')->nullable()->useCurrent();
            });
        }

        if (! Schema::hasTable('library_fine_receipt')) {
            Schema::create('library_fine_receipt', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('receipt_number', 30)->nullable()->unique('uq_fine_receipt_number');
                $t->string('idempotency_key', 64)->unique('uq_fine_receipt_idem');
                $t->unsignedBigInteger('session_id')->index('idx_fine_receipt_session');
                $t->unsignedBigInteger('patron_id')->index('idx_fine_receipt_patron');
                $t->string('currency', 3);
                $t->decimal('total_amount', 10, 2);
                $t->string('payment_method', 30);
                $t->boolean('is_cash')->default(false);
                $t->string('payment_reference', 100)->nullable();
                $t->decimal('tendered_amount', 10, 2)->nullable();
                $t->decimal('change_given', 10, 2)->nullable();
                $t->integer('issued_by');
                $t->dateTime('issued_at');
                $t->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (! Schema::hasTable('library_fine_receipt_line')) {
            Schema::create('library_fine_receipt_line', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('receipt_id')->index('idx_fine_line_receipt');
                $t->unsignedBigInteger('fine_id')->index('idx_fine_line_fine');
                $t->decimal('amount', 10, 2);
                $t->decimal('balance_after', 10, 2);
            });
        }

        if (! Schema::hasTable('library_fine_waiver')) {
            Schema::create('library_fine_waiver', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('fine_id')->index('idx_fine_waiver_fine');
                $t->unsignedBigInteger('patron_id');
                $t->integer('branch_id')->nullable()->index('idx_fine_waiver_branch');
                $t->string('currency', 3);
                $t->decimal('amount', 10, 2);
                $t->text('reason');
                $t->integer('waived_by');
                $t->dateTime('waived_at')->index('idx_fine_waiver_at');
            });
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('ahg_dropdown')) {
            DB::unprepared((string) file_get_contents(dirname(__DIR__) . '/seed_dropdowns.sql'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_fine_waiver');
        Schema::dropIfExists('library_fine_receipt_line');
        Schema::dropIfExists('library_fine_receipt');
        Schema::dropIfExists('library_cash_session');
    }
};
