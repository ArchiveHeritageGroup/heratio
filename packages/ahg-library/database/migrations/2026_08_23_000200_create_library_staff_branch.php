<?php

/**
 * Which branch a member of counter staff is working at - #1473 Phase 2.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 gave transactions a branch. Nothing yet says which branch the person
 * at the counter is standing in, so every list still shows the whole service.
 *
 * There was nothing to reuse. `ahg_tenant.repository_id` maps an entire tenant
 * to one repository, which is the wrong grain - a multi-branch library service
 * is ONE tenant with MANY branches - and no user-to-repository link exists
 * anywhere else in the schema.
 *
 * Deliberately one row per user rather than a many-to-many roster. The question
 * this answers is "where is this person working now", which has one answer at a
 * time; `all_branches` covers the consortium supervisor who legitimately needs
 * to see across outlets. A staff-rostering model can come later without
 * invalidating this, because reads go through LibraryBranch rather than
 * touching the table directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('library_staff_branch')) {
            return;
        }

        Schema::create('library_staff_branch', function (Blueprint $table) {
            // user.id, not users.id - Heratio authenticates against the AtoM-style
            // `user` table (AhgCore\Models\User extends Actor); `users` is the
            // unused Laravel default and is empty.
            $table->integer('user_id')->primary()->comment('user.id');
            $table->integer('branch_id')->nullable()
                ->comment('repository.id - the branch this operator works at');
            $table->boolean('all_branches')->default(false)
                ->comment('Consortium staff: may see across every branch');
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index('branch_id', 'idx_staff_branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_staff_branch');
    }
};
