<?php

/**
 * Circulation schema for the library module (#1093).
 *
 * Creates the tables the circulation + patron services already reference but
 * which previously only existed in ad-hoc PSIS migration SQL / the SQLite test
 * fixture (packages/ahg-library/tests/__fixtures__/schema.sql). Column names
 * and types mirror the live `heratio` schema exactly so this migration is a
 * faithful, idempotent reproduction:
 *
 *   - library_patron       borrower records (settings-driven defaults)
 *   - library_copy         physical item copies (barcode-addressable)
 *   - library_checkout     active / historical loans
 *   - library_hold         hold (reservation) queue against a library_item
 *   - library_fine         overdue + manual fines
 *   - library_loan_rule    per (material_type, patron_type) loan policy
 *
 * Every CREATE is wrapped in Schema::hasTable() so it is a no-op on installs
 * where the live tables already exist, and a clean create on fresh installs /
 * the migration-only test database (RefreshDatabase). For the test database
 * the two upstream catalogue dependencies (library_item, information_object_i18n)
 * live in database/core/*.sql rather than a migration, so minimal guarded
 * stubs are created here too - guarded so they never touch the real catalogue.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Catalogue dependency stubs (test/fresh-install only) ────────────
        // library_item + information_object_i18n are owned by database/core/*.sql
        // on a real install. Guard so we only create them when absent.
        if (!Schema::hasTable('information_object_i18n')) {
            Schema::create('information_object_i18n', function (Blueprint $table) {
                $table->unsignedBigInteger('id');
                $table->string('title', 500)->nullable();
                $table->string('culture', 16)->default('en');
                $table->index(['id', 'culture'], 'idx_io_i18n_id_culture');
            });
        }

        if (!Schema::hasTable('library_item')) {
            Schema::create('library_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('information_object_id')->nullable();
                $table->string('material_type', 50)->default('monograph');
                $table->string('call_number', 100)->nullable();
                $table->string('classification_scheme', 50)->nullable();
                $table->string('isbn', 17)->nullable();
                $table->string('issn', 9)->nullable();
                $table->string('barcode', 50)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('information_object_id', 'idx_li_io');
                $table->index('material_type', 'idx_li_material');
            });
        }

        // ── library_loan_rule ───────────────────────────────────────────────
        if (!Schema::hasTable('library_loan_rule')) {
            Schema::create('library_loan_rule', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('material_type', 50);
                $table->string('patron_type', 30)->default('*')->comment('* = all patron types');
                $table->smallInteger('loan_period_days')->unsigned()->default(14);
                $table->smallInteger('renewal_period_days')->unsigned()->default(14);
                $table->smallInteger('max_renewals')->unsigned()->default(2);
                $table->decimal('fine_per_day', 10, 2)->default(1.00);
                $table->decimal('fine_cap', 10, 2)->nullable()->comment('Max fine for this type');
                $table->smallInteger('grace_period_days')->unsigned()->default(0);
                $table->boolean('is_loanable')->default(true);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->unique(['material_type', 'patron_type'], 'uk_type_patron');
                $table->index('material_type', 'idx_material');
            });
        }

        // ── library_patron ──────────────────────────────────────────────────
        if (!Schema::hasTable('library_patron')) {
            Schema::create('library_patron', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('actor_id')->nullable()->comment('FK to actor table');
                $table->string('card_number', 50);
                $table->string('patron_type', 30)->default('public');
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 255)->nullable();
                $table->string('phone', 50)->nullable();
                $table->text('address')->nullable();
                $table->string('institution', 255)->nullable();
                $table->string('department', 100)->nullable();
                $table->string('id_number', 50)->nullable()->comment('National ID or student number');
                $table->date('date_of_birth')->nullable();
                $table->date('membership_start');
                $table->date('membership_expiry')->nullable();
                $table->smallInteger('max_checkouts')->unsigned()->default(5);
                $table->smallInteger('max_renewals')->unsigned()->default(2);
                $table->smallInteger('max_holds')->unsigned()->default(3);
                $table->string('borrowing_status', 20)->default('active');
                $table->text('suspension_reason')->nullable();
                $table->date('suspension_until')->nullable();
                $table->decimal('total_fines_owed', 10, 2)->default(0.00);
                $table->decimal('total_fines_paid', 10, 2)->default(0.00);
                $table->unsignedInteger('total_checkouts')->default(0);
                $table->date('last_activity_date')->nullable();
                $table->string('photo_url', 500)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->unique('card_number', 'uk_patron_card');
                $table->index('actor_id', 'idx_patron_actor');
                $table->index('patron_type', 'idx_patron_type');
                $table->index('borrowing_status', 'idx_patron_status');
                $table->index(['last_name', 'first_name'], 'idx_patron_name');
                $table->index('email', 'idx_patron_email');
                $table->index('membership_expiry', 'idx_patron_expiry');
            });
        }

        // ── library_copy ────────────────────────────────────────────────────
        if (!Schema::hasTable('library_copy')) {
            Schema::create('library_copy', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('library_item_id');
                $table->smallInteger('copy_number')->unsigned()->default(1);
                $table->string('barcode', 50)->nullable();
                $table->string('accession_number', 50)->nullable();
                $table->string('call_number_suffix', 20)->nullable()->comment('e.g. c.2, v.3');
                $table->string('shelf_location', 100)->nullable();
                $table->string('branch', 100)->nullable()->comment('Library branch/location');
                $table->string('status', 30)->default('available');
                $table->string('condition_grade', 30)->nullable();
                $table->text('condition_notes')->nullable();
                $table->string('acquisition_method', 50)->nullable();
                $table->date('acquisition_date')->nullable();
                $table->decimal('acquisition_cost', 15, 2)->nullable();
                $table->string('acquisition_source', 255)->nullable()->comment('vendor or donor');
                $table->date('withdrawal_date')->nullable();
                $table->text('withdrawal_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->unique('barcode', 'uk_copy_barcode');
                $table->index('library_item_id', 'idx_copy_item');
                $table->index('status', 'idx_copy_status');
                $table->index('branch', 'idx_copy_branch');
                $table->index('accession_number', 'idx_copy_accession');
            });
        }

        // ── library_checkout ────────────────────────────────────────────────
        if (!Schema::hasTable('library_checkout')) {
            Schema::create('library_checkout', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('copy_id');
                $table->unsignedBigInteger('patron_id');
                $table->dateTime('checkout_date');
                $table->date('due_date');
                $table->dateTime('return_date')->nullable();
                $table->smallInteger('renewed_count')->unsigned()->default(0);
                $table->string('status', 30)->default('active');
                $table->text('checkout_notes')->nullable();
                $table->text('return_notes')->nullable();
                $table->string('return_condition', 30)->nullable();
                $table->unsignedInteger('checked_out_by')->nullable()->comment('Staff user_id');
                $table->unsignedInteger('checked_in_by')->nullable()->comment('Staff user_id');
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->index('copy_id', 'idx_checkout_copy');
                $table->index('patron_id', 'idx_checkout_patron');
                $table->index('status', 'idx_checkout_status');
                $table->index('due_date', 'idx_checkout_due');
                $table->index('checkout_date', 'idx_checkout_date');
            });
        }

        // ── library_hold ────────────────────────────────────────────────────
        if (!Schema::hasTable('library_hold')) {
            Schema::create('library_hold', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('library_item_id');
                $table->unsignedBigInteger('patron_id');
                $table->dateTime('hold_date');
                $table->date('expiry_date')->nullable()->comment('Hold expires if not picked up');
                $table->string('pickup_branch', 100)->nullable();
                $table->smallInteger('queue_position')->unsigned()->default(1);
                $table->string('status', 30)->default('pending');
                $table->boolean('notification_sent')->default(false);
                $table->dateTime('notification_date')->nullable();
                $table->dateTime('fulfilled_date')->nullable();
                $table->dateTime('cancelled_date')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->index('library_item_id', 'idx_hold_item');
                $table->index('patron_id', 'idx_hold_patron');
                $table->index('status', 'idx_hold_status');
                $table->index(['library_item_id', 'queue_position'], 'idx_hold_queue');
            });
        }

        // ── library_fine ────────────────────────────────────────────────────
        if (!Schema::hasTable('library_fine')) {
            Schema::create('library_fine', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('patron_id');
                $table->unsignedBigInteger('checkout_id')->nullable();
                $table->string('fine_type', 30)->default('overdue');
                $table->decimal('amount', 10, 2);
                $table->decimal('paid_amount', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('ZAR');
                $table->string('status', 20)->default('outstanding');
                $table->text('description')->nullable();
                $table->date('fine_date');
                $table->dateTime('payment_date')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->unsignedInteger('waived_by')->nullable()->comment('Staff user_id who waived');
                $table->dateTime('waived_date')->nullable();
                $table->text('waive_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->index('patron_id', 'idx_fine_patron');
                $table->index('checkout_id', 'idx_fine_checkout');
                $table->index('status', 'idx_fine_status');
                $table->index('fine_type', 'idx_fine_type');
                $table->index('fine_date', 'idx_fine_date');
            });
        }
    }

    public function down(): void
    {
        // Only drop the circulation tables this migration owns. Leave the
        // catalogue stubs (library_item / information_object_i18n) in place -
        // on a real install they belong to the core schema, not this migration.
        Schema::dropIfExists('library_fine');
        Schema::dropIfExists('library_hold');
        Schema::dropIfExists('library_checkout');
        Schema::dropIfExists('library_copy');
        Schema::dropIfExists('library_patron');
        Schema::dropIfExists('library_loan_rule');
    }
};
