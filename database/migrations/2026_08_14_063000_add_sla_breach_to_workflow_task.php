<?php

/**
 * Add the SLA-breach columns `ahg:workflow-sla-check` has always tried to write.
 *
 * The command flagged breaches with `sla_breach` / `sla_breach_at`, but neither
 * column exists on `ahg_workflow_task` - one of several ways that command was
 * written against a schema that never shipped. The query is fixed alongside this;
 * these columns give it somewhere to record the result.
 *
 * Additive and nullable, so nothing existing changes: a task with no breach reads
 * as 0 / NULL, exactly as it did when the columns were absent.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_workflow_task')) {
            return;
        }

        Schema::table('ahg_workflow_task', function (Blueprint $table) {
            if (! Schema::hasColumn('ahg_workflow_task', 'sla_breach')) {
                $table->boolean('sla_breach')->default(0)->after('status');
            }
            if (! Schema::hasColumn('ahg_workflow_task', 'sla_breach_at')) {
                $table->timestamp('sla_breach_at')->nullable()->after('sla_breach');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ahg_workflow_task')) {
            return;
        }

        Schema::table('ahg_workflow_task', function (Blueprint $table) {
            foreach (['sla_breach_at', 'sla_breach'] as $col) {
                if (Schema::hasColumn('ahg_workflow_task', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
