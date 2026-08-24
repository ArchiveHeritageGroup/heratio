<?php

/**
 * Columns the Research Tools forms collect - #1481.
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
 * The Source Assessment form has existed, complete, with no route and no
 * controller action behind it. Of its seven fields only two - source_type and
 * completeness - had a column; the rest had nowhere to go.
 *
 * Column names match the form field names exactly, which is the rule applied
 * throughout #1478: divergence between a form field and its column is the root
 * cause of that whole defect class, and matching them lets the scanner prove
 * they stay wired.
 *
 * `bias` is added rather than folded into the existing `bias_context`. They are
 * different things: bias is a graded rating (none/low/moderate/high/extreme)
 * and bias_context is free prose explaining it. Collapsing a rating into a text
 * field would lose the grade and make it unqueryable.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'provenance'         => 'text',      // where the source came from
        'authenticity_notes' => 'text',      // doubts about the source being what it claims
        'reliability'        => 'tinyint',   // 1-5
        'bias'               => 'string:20', // none|low|moderate|high|extreme
    ];

    public function up(): void
    {
        if (! Schema::hasTable('research_source_assessment')) {
            return;
        }

        Schema::table('research_source_assessment', function (Blueprint $t) {
            foreach (self::COLUMNS as $name => $spec) {
                if (Schema::hasColumn('research_source_assessment', $name)) {
                    continue;
                }
                [$type, $len] = array_pad(explode(':', $spec), 2, null);
                match ($type) {
                    'text'    => $t->text($name)->nullable(),
                    'tinyint' => $t->unsignedTinyInteger($name)->nullable(),
                    default   => $t->string($name, (int) $len)->nullable(),
                };
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('research_source_assessment')) {
            return;
        }

        $present = array_values(array_filter(
            array_keys(self::COLUMNS),
            static fn ($c) => Schema::hasColumn('research_source_assessment', $c)
        ));

        if ($present !== []) {
            Schema::table('research_source_assessment', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};
