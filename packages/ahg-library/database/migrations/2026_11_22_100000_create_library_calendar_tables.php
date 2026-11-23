<?php

/**
 * The circulation calendar axis - #1475 item 1: weekly opening hours and dated
 * closures, so due dates avoid days a branch is shut and fines do not accrue
 * across them. Mirrored in database/core/04a_library_calendar.sql.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_opening_hours')) {
            Schema::create('library_opening_hours', function (Blueprint $table) {
                $table->id();
                $table->integer('branch_id')->default(0)->comment('repository.id, or 0 = all branches');
                $table->unsignedTinyInteger('weekday')->comment('ISO-8601: 1 = Monday, 7 = Sunday');
                $table->boolean('is_open')->default(true);
                $table->time('opens_at')->nullable();
                $table->time('closes_at')->nullable();
                $table->timestamps();
                $table->unique(['branch_id', 'weekday'], 'uk_library_hours_branch_day');
            });
        }

        if (! Schema::hasTable('library_closure')) {
            Schema::create('library_closure', function (Blueprint $table) {
                $table->id();
                $table->integer('branch_id')->default(0)->comment('repository.id, or 0 = all branches');
                $table->string('closure_type', 50)->comment('ahg_dropdown library_closure_type');
                $table->string('name', 150);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('recurs_annually')->default(false);
                $table->text('notes')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->index(['branch_id', 'start_date'], 'idx_library_closure_branch');
            });
        }

        if (Schema::hasTable('ahg_dropdown')) {
            foreach ([
                ['public_holiday', 'Public holiday', 10, 1],
                ['scheduled', 'Scheduled closure', 20, 0],
                ['stocktake', 'Stocktake', 30, 0],
                ['emergency', 'Emergency closure', 40, 0],
            ] as [$code, $label, $sort, $default]) {
                DB::table('ahg_dropdown')->insertOrIgnore([
                    'taxonomy' => 'library_closure_type',
                    'taxonomy_label' => 'Library Closure Type',
                    'taxonomy_section' => 'library',
                    'code' => $code,
                    'label' => $label,
                    'sort_order' => $sort,
                    'is_default' => $default,
                    'is_active' => 1,
                ]);
            }
        }
    }

    /** Deliberately empty: closures are operator data and are not dropped on rollback. */
    public function down(): void
    {
    }
};
