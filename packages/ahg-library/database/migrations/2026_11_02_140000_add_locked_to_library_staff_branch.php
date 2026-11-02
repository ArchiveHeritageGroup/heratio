<?php

/**
 * An administrator can hold a member of staff to one branch - #1473.
 *
 * Until now any operator could pick any branch at the desk, which suits a
 * public-library service and not a consortium whose outlets are contractually
 * separate. A locked row ignores the desk selector: the operator works at the
 * branch (or across all branches) an administrator set.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_staff_branch') || Schema::hasColumn('library_staff_branch', 'locked')) {
            return;
        }

        Schema::table('library_staff_branch', function (Blueprint $table) {
            $table->boolean('locked')->default(false)->after('all_branches')
                ->comment('Set by an administrator: the operator cannot change branch');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('library_staff_branch', 'locked')) {
            Schema::table('library_staff_branch', fn (Blueprint $table) => $table->dropColumn('locked'));
        }
    }
};
