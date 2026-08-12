<?php

/**
 * #1464 - give rights_record the two fields the extended_rights write path
 * handles and it lacked, so the repoint loses nothing.
 *
 *   rights_holder_uri - an authority URI for the holder (VIAF, ISNI, a local
 *                       actor page). extended_rights carried it; rights_record
 *                       had only the free-text holder, so repointing writes
 *                       without this would silently drop it.
 *   is_primary        - which right is the headline one for an object. The
 *                       backfill writes one record per object so it is moot
 *                       today, but the edit form exposes the concept and
 *                       nothing should regress because the target model is
 *                       newer.
 *
 * Additive and nullable; is_primary defaults to 1 so existing rows read as the
 * primary right, matching what the backfill produced.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rights_record')) {
            return;
        }

        if (! Schema::hasColumn('rights_record', 'rights_holder_uri')) {
            DB::statement("ALTER TABLE `rights_record` ADD COLUMN `rights_holder_uri` VARCHAR(500) NULL COMMENT 'authority URI for the rights holder (VIAF/ISNI/local actor)' AFTER `copyright_holder`");
        }

        if (! Schema::hasColumn('rights_record', 'is_primary')) {
            DB::statement("ALTER TABLE `rights_record` ADD COLUMN `is_primary` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'headline right for the object'");
        }
    }

    public function down(): void
    {
        // Not reverted: dropping these would discard holder URIs captured since.
    }
};
