<?php

/**
 * Expected return date for a material checkout - #1478.
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
 * The custody checkout form collects an expected return date and the return
 * verification screen displays it back. research_material_request records
 * checkout_confirmed_at, return_verified_at and return_condition but has never
 * had anywhere to put the date the item is DUE back - so the field was
 * collected and discarded, and the verify screen's "Expected Return" box read
 * empty on every return.
 *
 * The column name matches the form field name exactly (`expected_return`),
 * which is the rule applied throughout #1478.
 *
 * It is a DATE and not a datetime: a reading-room item is due back on a day,
 * and storing a spurious 00:00:00 would invite comparisons that are wrong by
 * up to a day.
 */
return new class extends Migration
{
    private const TABLE = 'research_material_request';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'expected_return')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $t) {
            $t->date('expected_return')->nullable()->after('checkout_confirmed_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'expected_return')) {
            return;
        }

        Schema::table(self::TABLE, fn (Blueprint $t) => $t->dropColumn('expected_return'));
    }
};
