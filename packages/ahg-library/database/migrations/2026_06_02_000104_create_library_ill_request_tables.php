<?php

/**
 * ILL request + audit schema, aligned to LibraryIllService (#1093).
 *
 * library_ill_request only existed in packages/ahg-library/database/install.sql
 * (Phase-1 shape) plus the EDI-fields ALTER migration, neither of which runs in
 * the migration-only test database. This migration reproduces the column set
 * LibraryIllService + EdiAdapter actually read/write, guarded with hasTable so
 * it is a no-op on any install where the table already exists (including the
 * live MySQL instance, whose historic PSIS shape this migration deliberately
 * does NOT touch). It also creates library_ill_audit, the transition trail the
 * service writes to when present.
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
        if (!Schema::hasTable('library_ill_request')) {
            Schema::create('library_ill_request', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('ill_number', 50);
                $table->string('type', 20)->default('borrow')->comment('borrow|lend');
                // EDI-extended descriptors (from the EDI-fields ALTER migration).
                $table->string('request_type', 20)->default('BORROW');
                $table->string('borrowing_protocol', 20)->default('AARC');
                $table->string('material_type', 30)->default('BOOK');

                $table->string('title', 500)->default('');
                $table->string('author', 255)->default('');
                $table->string('isbn', 32)->nullable();
                $table->string('issn', 32)->nullable();
                $table->string('volume', 64)->nullable();
                $table->string('issue', 64)->nullable();
                $table->string('pages', 64)->nullable();
                $table->string('citation', 500)->nullable();
                $table->text('lender_string')->nullable();
                $table->string('edition', 100)->nullable();
                $table->string('publication_year', 10)->nullable();

                $table->string('library_name', 255)->default('')->comment('Counterparty library');
                $table->string('library_symbol', 50)->nullable();
                $table->unsignedBigInteger('requester_library_id')->nullable();
                $table->unsignedBigInteger('responder_library_id')->nullable();
                $table->unsignedBigInteger('trading_partner_id')->nullable();
                $table->unsignedBigInteger('patron_id')->nullable()->comment('FK library_patron (borrow direction)');

                $table->date('request_date')->nullable();
                $table->date('needed_by_date')->nullable();
                $table->date('due_date')->nullable();

                $table->string('status', 32)->default('pending');
                $table->string('edi_message_id', 50)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_reason', 200)->nullable();

                $table->unsignedInteger('renewal_count')->default(0);
                $table->unsignedTinyInteger('max_renewals')->default(2);
                $table->decimal('cost_amount', 10, 2)->nullable();
                $table->string('cost_currency', 3)->nullable();
                $table->string('shipping_method', 50)->nullable();
                $table->string('tracking_number', 100)->nullable();

                $table->text('requester_note')->nullable();
                $table->text('responder_note')->nullable();
                $table->text('staff_note')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('opac_suppress')->default(false);

                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique('ill_number', 'uk_library_ill_number');
                $table->index('status', 'idx_library_ill_status');
                $table->index('type', 'idx_library_ill_type');
                $table->index('patron_id', 'idx_library_ill_patron');
                $table->index('request_date', 'idx_library_ill_request_date');
                $table->index('trading_partner_id', 'idx_library_ill_partner');
            });
        }

        if (!Schema::hasTable('library_ill_audit')) {
            Schema::create('library_ill_audit', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('ill_number', 50);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('description', 255)->nullable();
                $table->string('changed_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('ill_number', 'idx_ill_audit_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_ill_audit');
        Schema::dropIfExists('library_ill_request');
    }
};
