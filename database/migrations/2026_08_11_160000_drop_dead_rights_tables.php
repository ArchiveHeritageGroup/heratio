<?php

/**
 * #1464 - remove dead and duplicated rights tables.
 *
 * Two groups:
 *
 * 1. DEAD. Zero rows on every instance checked, and no code anywhere reads or
 *    writes them - the only references are the CREATE TABLE statements in
 *    install.sql / the core schema dump that produced them. They have never
 *    held anything.
 *
 * 2. DUPLICATE CC VOCABULARY. `creative_commons_license` (+ its _i18n) holds
 *    the same eight Creative Commons licences as `rights_cc_license`, with the
 *    same ids (1=CC0-1.0 … 8=PDM-1.0) and the same meaning under renamed
 *    columns (allows_adaptation/allows_derivatives,
 *    requires_sharealike/requires_share_alike). rights_cc_license is the one
 *    extended_rights.creative_commons_license_id actually resolves against and
 *    the one ExtendedRightsService/RightsHolderService already join, so it wins
 *    and the other goes. Because the ids match, the sole consumer
 *    (ExtendedRightsController) was repointed with no data remapping.
 *
 * SAFETY: a table is only dropped when it is empty, except the two CC tables
 * which are dropped knowingly as superseded duplicates. If a dead table turns
 * out to hold rows on some instance, this leaves it alone and logs - losing
 * data to a cleanup migration would be far worse than leaving a stale table.
 *
 * Not reverted: down() would recreate empty tables that nothing uses, which is
 * not a restore. The pre-drop dumps taken at release time are the restore path.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dead: dropped only if empty. */
    private const DEAD = [
        'donor_agreement_right',
        'donor_agreement_rights',
        'donor_rights_application_log',
        'rights_derivative_log',
        'rights_derivative_rule',
        'extended_rights_batch_log',
        'heritage_embargo',
        'embargo_i18n',
    ];

    /** Superseded by rights_cc_license: dropped even though populated. */
    private const SUPERSEDED = [
        'creative_commons_license_i18n',
        'creative_commons_license',
    ];

    public function up(): void
    {
        foreach (self::DEAD as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = (int) DB::table($table)->count();
            if ($rows > 0) {
                Log::warning("#1464 migration: kept `{$table}` - expected empty but holds {$rows} row(s). Review before removing.");

                continue;
            }

            Schema::drop($table);
        }

        // Only retire the duplicate once the survivor is actually present, so a
        // partially-installed instance cannot end up with neither.
        if (Schema::hasTable('rights_cc_license')) {
            foreach (self::SUPERSEDED as $table) {
                if (Schema::hasTable($table)) {
                    Schema::drop($table);
                }
            }
        } else {
            Log::warning('#1464 migration: rights_cc_license missing, so creative_commons_license was left in place.');
        }
    }

    public function down(): void
    {
        // Deliberately empty - see the class docblock.
    }
};
