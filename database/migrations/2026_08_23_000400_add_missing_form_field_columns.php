<?php

/**
 * Columns for form fields that had nowhere to save - #1478.
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
 * Four form fields collected input that no column could hold. Unlike the
 * renames in the same release, these are not misnamed - the concept was simply
 * never added to the schema, so the input was discarded on every save.
 *
 * The security-clearance ones are the reason this is worth doing rather than
 * deleting the fields: a clearance record that asks who vetted the person, when
 * and under what reference, and then throws the answers away, is a worse
 * artefact than one that never asked. Vetting evidence is the substance of a
 * clearance decision.
 *
 * `display_as_compound` is deliberately NOT here. It appears in three views and
 * is meant to change how child objects render, not merely to be stored - adding
 * a column nothing reads would recreate exactly the "a number nothing enforces"
 * problem that #1477 was filed for. It is tracked separately.
 */
return new class extends Migration
{
    /** table => [column => Blueprint method + args] */
    private const COLUMNS = [
        'user_security_clearance' => [
            'vetting_authority' => 'string:255',   // who carried out the vetting
            'vetting_date'      => 'date',
            'vetting_reference' => 'string:255',   // the vetting authority's own reference
        ],
        'repository' => [
            'repository_type' => 'string:100',
        ],
        'ahg_vendor_transactions' => [
            'completion_date' => 'date',           // work finished; distinct from actual_return_date
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $name => $spec) {
                    if (Schema::hasColumn($table, $name)) {
                        continue;
                    }
                    [$type, $len] = array_pad(explode(':', $spec), 2, null);
                    match ($type) {
                        'text'   => $t->text($name)->nullable(),
                        'date'   => $t->date($name)->nullable(),
                        default  => $t->string($name, (int) $len)->nullable(),
                    };
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter(
                array_keys($columns),
                static fn ($c) => Schema::hasColumn($table, $c)
            ));
            if ($present !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
            }
        }
    }
};
