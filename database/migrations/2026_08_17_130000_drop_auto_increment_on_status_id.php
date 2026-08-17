<?php

/**
 * Take AUTO_INCREMENT off `status.id` so a missing id fails loudly (#1470).
 *
 * `status` is a class-table-inheritance child of `object`: its id must be the id
 * of an `object` row with class_name 'QubitStatus'. AUTO_INCREMENT on that column
 * meant any insert omitting the id silently got a plausible-looking number from
 * status's own counter instead - and nothing complained. That is how this went
 * unnoticed since at least May: 417 rows on heratio.org pointing at no object,
 * 9,621 on dev pointing at an unrelated one, and eventually a user-facing 500 on
 * the edit form when the two id spaces collided.
 *
 * With the column NOT NULL and no default, the same mistake now fails at the
 * first insert with "Field 'id' doesn't have a default value" - loud, immediate,
 * and attributable. STRICT_TRANS_TABLES is set on the server and on Laravel's
 * connection (verified on all three instances), so it errors rather than quietly
 * inserting 0.
 *
 * PREREQUISITES, all verified before writing this:
 *
 *  - All twenty PHP call sites allocate an object row first (v1.154.622), via
 *    AhgCore\Support\StatusRow.
 *  - Every existing row already sits on its own QubitStatus object (v1.154.623
 *    and v1.154.624) - 9,906 on dev, 827 on heratio.org, 10 on sasa, none wrong.
 *  - The only raw-SQL insert that omits the id is the July 2026 actor-embargo
 *    backfill migration, which runs EARLIER than this one, so a fresh install
 *    still gets its rows and the later rehome migration repairs their ids before
 *    this drop takes effect.
 *  - database/core/00_core_schema.sql deliberately KEEPS the AUTO_INCREMENT, for
 *    the same reason: that July migration needs it at the point it runs. This
 *    migration is what removes it afterwards.
 *
 * If a path was missed despite all that, the failure will be a 500 on whatever
 * action reaches it - which is the trade being made deliberately: a loud break
 * beats corrupting a shared id space for months.
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
    public function up(): void
    {
        if (! Schema::hasTable('status')) {
            return;
        }

        $col = $this->idColumn();
        if (! $col) {
            return;
        }
        if (! str_contains(strtolower((string) ($col->EXTRA ?? '')), 'auto_increment')) {
            return;   // already done
        }

        // Refuse if any row is still misplaced: dropping the safety net while the
        // data is inconsistent would harden the wrong state.
        $bad = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->where(function ($w) {
                $w->whereNull('o.id')->orWhere('o.class_name', '!=', 'QubitStatus');
            })
            ->count();

        if ($bad > 0) {
            Log::warning("Not dropping AUTO_INCREMENT on status.id: {$bad} row(s) are not on a QubitStatus object row. Run the rehome migration first.");

            return;
        }

        // Preserve the exact type and nullability; MODIFY leaves the PRIMARY KEY
        // alone. COLUMN_TYPE is read back rather than hardcoded so this does not
        // silently widen or narrow the column on an instance that differs.
        $type = (string) $col->COLUMN_TYPE;
        DB::statement("ALTER TABLE `status` MODIFY `id` {$type} NOT NULL");

        Log::info("AUTO_INCREMENT removed from status.id ({$type} NOT NULL); a missing id now fails at insert time.");
    }

    public function down(): void
    {
        if (! Schema::hasTable('status')) {
            return;
        }
        $col = $this->idColumn();
        if (! $col || str_contains(strtolower((string) ($col->EXTRA ?? '')), 'auto_increment')) {
            return;
        }
        DB::statement('ALTER TABLE `status` MODIFY `id` '.$col->COLUMN_TYPE.' NOT NULL AUTO_INCREMENT');
    }

    private function idColumn(): ?object
    {
        try {
            return DB::selectOne(
                'SELECT COLUMN_TYPE, EXTRA FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['status', 'id']
            );
        } catch (\Throwable) {
            return null;
        }
    }
};
