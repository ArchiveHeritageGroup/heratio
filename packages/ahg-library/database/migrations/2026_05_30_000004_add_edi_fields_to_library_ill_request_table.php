<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_ill_request')) {
            return;
        }

        Schema::table('library_ill_request', function (Blueprint $table) {
            // Augment Phase-1 table - add EDI / ILL-EDI columns only if missing.
            // VARCHAR (not ENUM) per the project dropdown rule; positional
            // ->after() clauses removed because the live (PSIS-shaped) table does
            // not carry the columns the originals referenced, which made the
            // migration abort. Column order is cosmetic.

            if (!Schema::hasColumn('library_ill_request', 'request_type')) {
                $table->string('request_type', 30)->default('BORROW');
            }

            if (!Schema::hasColumn('library_ill_request', 'borrowing_protocol')) {
                $table->string('borrowing_protocol', 20)->default('AARC');
            }

            if (!Schema::hasColumn('library_ill_request', 'material_type')) {
                $table->string('material_type', 30)->default('BOOK');
            }

            if (!Schema::hasColumn('library_ill_request', 'responder_library_id')) {
                $table->unsignedBigInteger('responder_library_id')->nullable()
                    ->comment('FK library_vendor.id - the lending library');
            }

            if (!Schema::hasColumn('library_ill_request', 'responder_note')) {
                $table->text('responder_note')->nullable();
            }

            if (!Schema::hasColumn('library_ill_request', 'citation')) {
                $table->string('citation', 500)->nullable();
            }

            if (!Schema::hasColumn('library_ill_request', 'lender_string')) {
                $table->text('lender_string')->nullable()
                    ->comment('Raw ISO-ILL string or bibliographic data string from lender');
            }

            if (!Schema::hasColumn('library_ill_request', 'edi_message_id')) {
                $table->string('edi_message_id', 50)->nullable()
                    ->comment('Cross-ref to EDI interchange sent/received');
            }

            if (!Schema::hasColumn('library_ill_request', 'needed_by_date')) {
                $table->date('needed_by_date')->nullable();
            }

            if (!Schema::hasColumn('library_ill_request', 'shipping_method')) {
                $table->string('shipping_method', 50)->nullable();
            }

            if (!Schema::hasColumn('library_ill_request', 'max_renewals')) {
                $table->unsignedTinyInteger('max_renewals')->default(2);
            }

            if (!Schema::hasColumn('library_ill_request', 'trading_partner_id')) {
                $table->unsignedBigInteger('trading_partner_id')->nullable()
                    ->comment('FK library_trading_partner.id - EDI partner used for this request');
            }

            if (!Schema::hasColumn('library_ill_request', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }

            if (!Schema::hasColumn('library_ill_request', 'closed_reason')) {
                $table->string('closed_reason', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('library_ill_request')) {
            return;
        }

        Schema::table('library_ill_request', function (Blueprint $table) {
            $cols = [
                'request_type', 'borrowing_protocol', 'material_type',
                'responder_library_id', 'responder_note', 'citation',
                'lender_string', 'edi_message_id', 'needed_by_date',
                'shipping_method', 'max_renewals', 'trading_partner_id',
                'closed_at', 'closed_reason',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('library_ill_request', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
