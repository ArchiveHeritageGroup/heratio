<?php

/**
 * Overdue notice templating + send log (#1093).
 *
 *   - library_notice_template   editable notice templates (subject + body with
 *                               {{placeholder}} tokens) keyed by notice_type
 *                               (overdue_1, overdue_2, overdue_final, hold_ready).
 *   - library_overdue_notice_log per-send audit row written by the
 *                               ahg:library-overdue-notices command so a patron
 *                               is not spammed with the same notice tier twice.
 *
 * VARCHAR (not ENUM) for notice_type / channel / status per the project rule.
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
        if (!Schema::hasTable('library_notice_template')) {
            Schema::create('library_notice_template', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('notice_type', 40)->comment('overdue_1|overdue_2|overdue_final|hold_ready');
                $table->string('channel', 20)->default('email')->comment('email|sms|print');
                $table->string('subject', 255);
                $table->text('body')->comment('plain-text body with {{token}} placeholders');
                $table->smallInteger('trigger_days_overdue')->unsigned()->default(0)
                    ->comment('min days past due before this tier fires (0 for hold_ready)');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->unique(['notice_type', 'channel'], 'uk_notice_type_channel');
                $table->index('is_active', 'idx_notice_active');
            });
        }

        if (!Schema::hasTable('library_overdue_notice_log')) {
            Schema::create('library_overdue_notice_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('checkout_id');
                $table->unsignedBigInteger('patron_id');
                $table->string('notice_type', 40);
                $table->string('channel', 20)->default('email');
                $table->string('recipient', 255)->nullable()->comment('email address / phone at send time');
                $table->smallInteger('days_overdue')->unsigned()->default(0);
                $table->string('status', 20)->default('sent')->comment('sent|failed|skipped');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->index('checkout_id', 'idx_notice_log_checkout');
                $table->index('patron_id', 'idx_notice_log_patron');
                $table->index(['checkout_id', 'notice_type'], 'idx_notice_log_checkout_type');
                $table->index('status', 'idx_notice_log_status');
            });
        }

        $this->seedTemplates();
    }

    /**
     * Seed the default escalation ladder. Idempotent via updateOrInsert.
     */
    protected function seedTemplates(): void
    {
        $templates = [
            [
                'notice_type' => 'overdue_1',
                'subject'     => 'Library item overdue: {{title}}',
                'body'        => "Dear {{patron_name}},\n\nOur records show that the following item is now overdue:\n\n  {{title}}\n  Barcode: {{barcode}}\n  Due date: {{due_date}}\n  Days overdue: {{days_overdue}}\n\nPlease return or renew it at your earliest convenience. An overdue fine of {{currency}} {{fine_per_day}} per day may apply.\n\nRegards,\n{{library_name}}",
                'trigger'     => 1,
            ],
            [
                'notice_type' => 'overdue_2',
                'subject'     => 'Second reminder - overdue item: {{title}}',
                'body'        => "Dear {{patron_name}},\n\nThis is a second reminder that the item below remains overdue:\n\n  {{title}}\n  Barcode: {{barcode}}\n  Due date: {{due_date}}\n  Days overdue: {{days_overdue}}\n  Fine to date: {{currency}} {{fine_amount}}\n\nPlease return the item to avoid further charges.\n\nRegards,\n{{library_name}}",
                'trigger'     => 7,
            ],
            [
                'notice_type' => 'overdue_final',
                'subject'     => 'FINAL NOTICE - overdue item: {{title}}',
                'body'        => "Dear {{patron_name}},\n\nThe following item is seriously overdue and your borrowing privileges may be suspended:\n\n  {{title}}\n  Barcode: {{barcode}}\n  Due date: {{due_date}}\n  Days overdue: {{days_overdue}}\n  Fine to date: {{currency}} {{fine_amount}}\n\nPlease contact the library immediately.\n\nRegards,\n{{library_name}}",
                'trigger'     => 21,
            ],
            [
                'notice_type' => 'hold_ready',
                'subject'     => 'Your hold is ready for pickup: {{title}}',
                'body'        => "Dear {{patron_name}},\n\nThe item you placed a hold on is now available for collection:\n\n  {{title}}\n\nPlease collect it by {{expiry_date}} before the hold expires.\n\nRegards,\n{{library_name}}",
                'trigger'     => 0,
            ],
        ];

        foreach ($templates as $t) {
            try {
                DB::table('library_notice_template')->updateOrInsert(
                    ['notice_type' => $t['notice_type'], 'channel' => 'email'],
                    [
                        'subject'              => $t['subject'],
                        'body'                 => $t['body'],
                        'trigger_days_overdue' => $t['trigger'],
                        'is_active'            => 1,
                        'updated_at'           => now(),
                    ]
                );
            } catch (\Throwable) {
                // table not ready - skip
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_overdue_notice_log');
        Schema::dropIfExists('library_notice_template');
    }
};
