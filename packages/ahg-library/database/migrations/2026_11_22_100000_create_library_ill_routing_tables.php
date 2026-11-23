<?php

/**
 * ILL supplier rota, routing attempts and inbound EDI log (#1475).
 *
 * Additive only. Attempt and outcome values are VARCHAR with their labels in
 * the Dropdown Manager.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_ill_rota_entry')) {
            Schema::create('library_ill_rota_entry', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('trading_partner_id');
                $table->unsignedSmallInteger('priority')->default(100);
                $table->unsignedSmallInteger('timeout_hours')->default(72);
                $table->string('material_types', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->char('inbound_token_hash', 64)->nullable();
                $table->timestamps();
                $table->unique('trading_partner_id', 'uk_ill_rota_partner');
                $table->unique('inbound_token_hash', 'uk_ill_rota_token');
                $table->index(['is_active', 'priority'], 'idx_ill_rota_rank');
            });
        }

        if (!Schema::hasTable('library_ill_routing_attempt')) {
            Schema::create('library_ill_routing_attempt', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ill_request_id');
                $table->unsignedBigInteger('trading_partner_id');
                $table->unsignedSmallInteger('sequence')->default(1);
                $table->string('status', 30);
                $table->unsignedTinyInteger('send_failures')->default(0);
                $table->string('edi_message_id', 50)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->unique(['ill_request_id', 'trading_partner_id'], 'uk_ill_attempt_partner');
                $table->index(['status', 'expires_at'], 'idx_ill_attempt_expiry');
                $table->index(['trading_partner_id', 'status'], 'idx_ill_attempt_partner');
                $table->index(['trading_partner_id', 'edi_message_id'], 'idx_ill_attempt_message');
            });
        }

        if (!Schema::hasTable('library_ill_edi_inbound')) {
            Schema::create('library_ill_edi_inbound', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('trading_partner_id');
                $table->char('payload_hash', 64);
                $table->unsignedBigInteger('ill_request_id')->nullable();
                $table->string('msg_ref', 100)->nullable();
                $table->string('ill_number', 50)->nullable();
                $table->string('profile', 20)->nullable();
                $table->string('raw_status', 100)->nullable();
                $table->string('decoded_status', 30)->nullable();
                $table->string('outcome', 30);
                $table->string('detail', 500)->nullable();
                $table->mediumText('raw_message');
                $table->timestamps();
                $table->unique(['trading_partner_id', 'payload_hash'], 'uk_ill_inbound_payload');
                $table->index('ill_request_id', 'idx_ill_inbound_request');
            });
        }

        $this->seedDropdowns();
    }

    public function down(): void
    {
        Schema::dropIfExists('library_ill_edi_inbound');
        Schema::dropIfExists('library_ill_routing_attempt');
        Schema::dropIfExists('library_ill_rota_entry');
    }

    private function seedDropdowns(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $taxonomies = [
            'library_ill_attempt_status' => ['ILL Routing Attempt Status', [
                'sent' => 'Awaiting reply', 'accepted' => 'Accepted', 'declined' => 'Declined',
                'timed_out' => 'Timed out', 'send_failed' => 'Send failed', 'withdrawn' => 'Withdrawn', 'manual' => 'Sent by hand',
            ]],
            'library_ill_inbound_outcome' => ['ILL Inbound Message Outcome', [
                'processing' => 'Processing', 'applied' => 'Applied', 'stale' => 'Late reply, not applied',
                'no_match' => 'No matching request', 'unmapped' => 'Status not understood',
                'ignored' => 'Needs staff review', 'invalid_transition' => 'Invalid status change',
            ]],
        ];

        foreach ($taxonomies as $taxonomy => [$label, $options]) {
            $order = 0;
            foreach ($options as $code => $text) {
                $order += 10;
                if (DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('code', $code)->exists()) {
                    continue;
                }
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => $taxonomy, 'taxonomy_label' => $label, 'taxonomy_section' => 'library',
                    'code' => $code, 'label' => $text, 'sort_order' => $order,
                    'is_default' => $order === 10 ? 1 : 0, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
};
