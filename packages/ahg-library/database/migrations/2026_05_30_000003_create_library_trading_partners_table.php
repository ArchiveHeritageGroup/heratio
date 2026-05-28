<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_trading_partner', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->string('edi_partner_code', 20)->unique();
            $table->enum('edi_type', ['EANCOM', 'X12', 'UN/EDIFACT', 'CUSTOM'])->default('EANCOM');
            $table->enum('message_profile', ['EANCOM_S93', 'EANCOM_S94', 'X12_850', 'CUSTOM'])->default('EANCOM_S93');
            $table->enum('endpoint_type', ['SFTP', 'AS2', 'HTTP_HTTPS', 'EMAIL', 'MANUAL'])->default('SFTP');
            $table->json('endpoint_config')->nullable();
            $table->string('outbound_directory', 255)->default('/outbox/');
            $table->string('inbound_directory', 255)->default('/inbox/');
            $table->boolean('acknowledgement_required')->default(true);
            $table->boolean('test_mode')->default(true);
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['edi_type', 'is_active'], 'idx_tp_edi_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_trading_partner');
    }
};
