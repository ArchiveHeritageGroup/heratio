<?php

/**
 * The CCO fields the gallery and museum edit forms were discarding - #1478.
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery and museum edit forms are built to Cataloguing Cultural Objects,
 * every field carrying its own help text and CCO citation. Thirty of those
 * fields had nowhere to go: absent from `museum_metadata`, absent from the
 * validators, and absent from the `$metaFields` whitelists in GalleryService
 * and MuseumService. A cataloguer filled them in, pressed Save, and the values
 * were discarded without an error - including `dimensions_display`, which the
 * markup annotates as REQUIRED, CCO 6.1.
 *
 * Lives in database/migrations rather than the package: ahg-museum has no
 * migrations directory and no provider calling loadMigrationsFrom(), so a
 * migration placed there would silently never run. The existing
 * add_icip_sensitivity_to_museum_metadata migration sits here for the same
 * reason and is the precedent followed.
 *
 * Column names match the form field names EXACTLY, deliberately. Name
 * divergence between a form and its column is the root cause of the whole
 * #1478 defect class, and matching them lets the scanner prove these stay
 * wired. It costs some near-duplication against columns that already exist for
 * adjacent concepts - `creator_qualifier` beside `attribution_qualifier`,
 * `dimensions` beside `dimensions_display` - and that is the deliberate trade:
 * an extra column is recoverable, whereas guessing a CCO mapping wrong and
 * silently writing "Gallery 3, Shelf B" into `current_location_repository`
 * would be a worse and much quieter failure. Consolidation is a follow-up on
 * #1478 for someone with the CCO mapping in front of them.
 *
 * All text, no numerics: every one of these is a text or textarea input, and
 * the measurement fields carry units ("45.5 cm"), so a decimal column would
 * reject what the form legitimately sends.
 *
 * `alternate_titles` is NOT here. That one was a plural typo - the singular
 * `information_object_i18n.alternate_title` already exists and is already
 * saved, so the form field is simply renamed to match.
 */
return new class extends Migration
{
    /**
     * All TEXT, none VARCHAR. `museum_metadata` already carries 90-odd columns
     * and its VARCHAR definitions alone account for 63,964 of InnoDB's
     * 65,535-byte row limit - 98% full. A VARCHAR counts toward that limit in
     * full; a TEXT costs only a ~20-byte pointer because its payload lives
     * off-page. The first attempt at this migration used VARCHAR and failed
     * with "Row size too large" partway through, which is also why up() below
     * converts any column left behind by that partial run.
     *
     * Nothing here needs an index or a length constraint, so TEXT costs
     * nothing real. Note for anyone extending this table: it is close enough
     * to the limit that the next VARCHAR may not fit either.
     */
    private const COLUMNS = [
        'work_type_qualifier',        // CCO 2.1.1
        'components_count',           // CCO 2.2 - free text, e.g. "3 panels"
        'title_language',             // CCO 3.1.2
        'creator_display',            // CCO 4.1 - display statement
        'attribution_qualifier',      // CCO 4.1.2
        'school_group',               // CCO 5.3
        'dimensions_display',         // CCO 6.1 - REQUIRED in the form
        'height_value',               // CCO 6.2
        'width_value',                // CCO 6.2
        'depth_value',                // CCO 6.2
        'weight_value',               // CCO 6.3
        'dimension_notes',            // CCO 6.4
        'materials_display',          // CCO 7.1 - REQUIRED in the form
        'subjects_depicted',          // CCO 8.2
        'iconography',                // CCO 8.3
        'named_subjects',             // CCO 8.4
        'impression_quality',         // CCO 10.4
        'condition_summary',          // CCO 12.1
        'location_within_repository', // CCO 13.2
    ];

    public function up(): void
    {
        if (! Schema::hasTable('museum_metadata')) {
            return;
        }

        // Clear up after the failed VARCHAR attempt. Only a column that exists,
        // is not already TEXT, and holds no data at all is dropped - so a
        // re-run can never discard catalogued values.
        foreach (self::COLUMNS as $name) {
            if (! Schema::hasColumn('museum_metadata', $name)) {
                continue;
            }
            $type = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'museum_metadata')
                ->where('column_name', $name)
                ->value('data_type');
            if ($type === 'text') {
                continue;
            }
            if (DB::table('museum_metadata')->whereNotNull($name)->exists()) {
                Log::warning("#1478 migration: museum_metadata.{$name} is {$type} and holds data; left as is.");
                continue;
            }
            Schema::table('museum_metadata', function (Blueprint $table) use ($name) {
                $table->dropColumn($name);
            });
        }

        Schema::table('museum_metadata', function (Blueprint $table) {
            foreach (self::COLUMNS as $name) {
                if (! Schema::hasColumn('museum_metadata', $name)) {
                    $table->text($name)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('museum_metadata')) {
            return;
        }

        $present = array_values(array_filter(
            self::COLUMNS,
            static fn ($c) => Schema::hasColumn('museum_metadata', $c)
        ));

        if ($present !== []) {
            Schema::table('museum_metadata', function (Blueprint $table) use ($present) {
                $table->dropColumn($present);
            });
        }
    }
};
