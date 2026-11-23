<?php

/**
 * SMS + print channels for overdue notices (#1474 item 2).
 *
 *   - library_patron_notice_preference  one chosen channel per patron; no row
 *                                       means the default (email).
 *   - library_notice_print_item         a rendered notice waiting for, or
 *                                       included in, a print run.
 *   - library_notice_print_batch        one print run (a PDF for counter
 *                                       collection or post).
 *
 * SMS and print templates are seeded INACTIVE so that deploying this changes
 * nothing until a librarian reviews and switches a template on.
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
        if (!Schema::hasTable('library_patron_notice_preference')) {
            Schema::create('library_patron_notice_preference', function (Blueprint $table) {
                $table->unsignedBigInteger('patron_id')->primary();
                $table->string('channel', 20)->comment('code from ahg_dropdown library_notice_channel');
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamp('updated_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('library_notice_print_batch')) {
            Schema::create('library_notice_print_batch', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('item_count')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('library_notice_print_item')) {
            Schema::create('library_notice_print_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->unsignedBigInteger('log_id')->nullable();
                $table->unsignedBigInteger('checkout_id');
                $table->unsignedBigInteger('patron_id');
                $table->string('notice_type', 40);
                $table->string('recipient_name', 255);
                $table->text('postal_address')->nullable();
                $table->string('subject', 255);
                $table->text('body');
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->unique(['checkout_id', 'notice_type'], 'uk_print_item_checkout_type');
                $table->index('batch_id', 'idx_print_item_batch');
            });
        }

        $this->seedDropdowns();
        $this->seedTemplates();
    }

    protected function seedDropdowns(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }
        $taxonomies = [
            'library_notice_channel' => ['Library Notice Channel', [
                'email' => 'Email', 'sms' => 'SMS', 'print' => 'Print',
            ]],
            'library_notice_status' => ['Library Notice Status', [
                'sent' => 'Sent', 'queued' => 'Queued for print', 'batched' => 'In a print run',
                'failed' => 'Failed', 'skipped' => 'Skipped',
            ]],
        ];
        foreach ($taxonomies as $taxonomy => [$label, $options]) {
            $sort = 10;
            foreach ($options as $code => $optionLabel) {
                if (!DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('code', $code)->exists()) {
                    DB::table('ahg_dropdown')->insert([
                        'taxonomy' => $taxonomy, 'taxonomy_label' => $label, 'taxonomy_section' => 'library',
                        'code' => $code, 'label' => $optionLabel, 'sort_order' => $sort,
                        'is_default' => $code === 'email' ? 1 : 0, 'is_active' => 1, 'created_at' => now(),
                    ]);
                }
                $sort += 10;
            }
        }
    }

    protected function seedTemplates(): void
    {
        if (!Schema::hasTable('library_notice_template')) {
            return;
        }
        $tiers = [
            'overdue_1'     => [1, 'Library item overdue: {{title}}', '{{library_name}}: "{{title}}" was due {{due_date}}. Please return or renew it.'],
            'overdue_2'     => [7, 'Second reminder - overdue item: {{title}}', '{{library_name}}: 2nd reminder. "{{title}}" is {{days_overdue}} days overdue. Fine to date {{currency}} {{fine_amount}}.'],
            'overdue_final' => [21, 'FINAL NOTICE - overdue item: {{title}}', '{{library_name}}: FINAL notice. "{{title}}" is {{days_overdue}} days overdue. Fine {{currency}} {{fine_amount}}. Please contact the library.'],
        ];
        $printBody = "Dear {{patron_name}},\n\nOur records show that the following item is overdue:\n\n  {{title}}\n  Barcode: {{barcode}}\n  Due date: {{due_date}}\n  Days overdue: {{days_overdue}}\n  Fine to date: {{currency}} {{fine_amount}}\n\nPlease return or renew it, or contact the library.\n\nRegards,\n{{branch_name}}";

        foreach ($tiers as $type => [$trigger, $subject, $smsBody]) {
            foreach (['sms' => $smsBody, 'print' => $printBody] as $channel => $body) {
                DB::table('library_notice_template')->insertOrIgnore([
                    'notice_type' => $type, 'channel' => $channel, 'subject' => $subject, 'body' => $body,
                    'trigger_days_overdue' => $trigger, 'is_active' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_notice_print_item');
        Schema::dropIfExists('library_notice_print_batch');
        Schema::dropIfExists('library_patron_notice_preference');
    }
};
