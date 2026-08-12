<?php

/**
 * #1464 - re-run the rights_record backfill immediately before the read repoint.
 *
 * v1.154.580 populated rights_record but left extended_rights as the write
 * target, so anything created between that release and this one exists only in
 * extended_rights. The repoint in this same release switches reads to
 * rights_record, and a record missed here would simply vanish from display.
 *
 * The backfill is idempotent and picks up stragglers - that property is what
 * makes the two-stage rollout safe, and this is the stage that uses it. It runs
 * before the repoint takes effect because migrations run at deploy time while
 * the code change lands with the same release.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $backfill = require __DIR__.'/2026_08_12_110000_backfill_rights_record.php';
        $backfill->up();
    }

    public function down(): void
    {
        // Nothing to undo - the backfill it delegates to is additive.
    }
};
