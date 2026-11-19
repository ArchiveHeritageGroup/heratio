<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1768 / #1773 / #1774: a basket becomes one access request that a
 * configurable workflow routes; staff may release it with conditions, or send
 * a quotation that releases the records only once it is paid.
 *  - access_request: conditions, quotation and the order that pays it
 *  - ahg_order: the access request an order pays for
 *  - an "Access request review" workflow, switched off until an
 *    administrator turns it on under Workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_request')) {
            Schema::table('access_request', function (Blueprint $t) {
                $add = fn (string $c) => ! Schema::hasColumn('access_request', $c);
                if ($add('conditions')) {
                    $t->text('conditions')->nullable();
                }
                if ($add('quote_amount')) {
                    $t->decimal('quote_amount', 10, 2)->nullable();
                }
                if ($add('quote_currency')) {
                    $t->string('quote_currency', 3)->nullable();
                }
                if ($add('quote_note')) {
                    $t->text('quote_note')->nullable();
                }
                if ($add('quoted_at')) {
                    $t->dateTime('quoted_at')->nullable();
                }
                if ($add('order_id')) {
                    $t->unsignedInteger('order_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('ahg_order') && ! Schema::hasColumn('ahg_order', 'access_request_id')) {
            Schema::table('ahg_order', function (Blueprint $t) {
                $t->unsignedInteger('access_request_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('ahg_workflow') && Schema::hasTable('ahg_workflow_step')
            && ! DB::table('ahg_workflow')->where('applies_to', 'access_request')->exists()) {
            $wf = DB::table('ahg_workflow')->insertGetId([
                'name' => 'Access request review',
                'description' => 'Routes every access request, including basket requests, through a check and a release decision. The last approval releases the records to the requester.',
                'scope_type' => 'global', 'trigger_event' => 'submit', 'applies_to' => 'access_request',
                'is_active' => 0, 'is_default' => 1, 'require_all_steps' => 1, 'allow_parallel' => 0,
                'notification_enabled' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([
                [1, 'Check the request', 'review', 'Check who is asking, why, and that the records may be released. Approve to pass it on, reject to refuse it.'],
                [2, 'Release decision', 'approval', 'Approve to release the records to the requester. To attach conditions or send a quotation instead, open the request itself.'],
            ] as [$order, $name, $type, $instructions]) {
                DB::table('ahg_workflow_step')->insert([
                    'workflow_id' => $wf, 'name' => $name, 'step_order' => $order, 'step_type' => $type,
                    'action_required' => 'approve_reject', 'pool_enabled' => 1, 'escalation_days' => 5,
                    'instructions' => $instructions, 'is_optional' => 0, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Additive only; nothing to undo safely.
    }
};
