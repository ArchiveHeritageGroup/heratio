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
            // Augment Phase-1 table — add EDI / ILL-EDI columns only if missing.

            if (!Schema::hasColumn('library_ill_request', 'request_type')) {
                $table->enum('request_type', ['BORROW', 'SUPPLY', 'PHOTOCOPY', 'LOAN_RENEWAL', 'STATUS_CHECK'])
                    ->default('BORROW')->after('type');
            }

            if (!Schema::hasColumn('library_ill_request', 'borrowing_protocol')) {
                $table->enum('borrowing_protocol', ['AARC', 'IFM', 'BLDSS', 'RLG', 'CUSTOM'])
                    ->default('AARC')->after('request_type');
            }

            if (!Schema::hasColumn('library_ill_request', 'material_type')) {
                $table->enum('material_type', ['BOOK', 'SERIAL_ISSUE', 'CONFERENCE_PAPER', 'THESIS', 'PATENT', 'REPORT', 'OTHER'])
                    ->default('BOOK')->after('borrowing_protocol');
            }

            if (!Schema::hasColumn('library_ill_request', 'responder_library_id')) {
                $table->unsignedBigInteger('responder_library_id')->nullable()
                    ->comment('FK library_vendors.id — the lending library')->after('requester_library_id');
            }

            if (!Schema::hasColumn('library_ill_request', 'responder_note')) {
                $table->text('responder_note')->nullable()->after('requester_note');
            }

            if (!Schema::hasColumn('library_ill_request', 'citation')) {
                $table->string('citation', 500)->nullable()->after('pages');
            }

            if (!Schema::hasColumn('library_ill_request', 'lender_string')) {
                $table->text('lender_string')->nullable()
                    ->comment('Raw IS0-ILL string or bibliographic data string from lender')->after('citation');
            }

            if (!Schema::hasColumn('library_ill_request', 'edi_message_id')) {
                $table->string('edi_message_id', 50)->nullable()
                    ->comment('Cross-ref to EDI interchange sent/received')->after('status');
            }

            if (!Schema::hasColumn('library_ill_request', 'needed_by_date')) {
                $table->date('needed_by_date')->nullable()->after('request_date');
            }

            if (!Schema::hasColumn('library_ill_request', 'shipping_method')) {
                $table->string('shipping_method', 50)->nullable()->after('cost_currency');
            }

            if (!Schema::hasColumn('library_ill_request', 'max_renewals')) {
                $table->unsignedTinyInteger('max_renewals')->default(2)->after('renewal_count');
            }

            if (!Schema::hasColumn('library_ill_request', 'trading_partner_id')) {
                $table->unsignedBigInteger('trading_partner_id')->nullable()
                    ->comment('FK library_trading_partner.id — EDI partner used for this request')->after('responder_library_id');
            }

            if (!Schema::hasColumn('library_ill_request', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('edi_message_id');
            }

            if (!Schema::hasColumn('library_ill_request', 'closed_reason')) {
                $table->string('closed_reason', 200)->nullable()->after('closed_at');
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
