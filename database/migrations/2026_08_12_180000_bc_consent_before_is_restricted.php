<?php

/**
 * #1464 - "BC Consent Before" becomes a restricting label.
 *
 * The label was added to the ICIP catalogue in v1.154.581 with a deliberately
 * non-restricting condition ('attribution'), because the TK migration had no
 * business deciding how a cultural label governs access - that belongs to the
 * archive and the source community. Johan has now decided: it restricts.
 *
 * 'restricted' is one of TermProtocolService::RESTRICTED, so from here
 * TermProtocolGate will withhold any object carrying this label from guests
 * across the show pages, browse, search, exports, OAI, RiC and the portable
 * bundle - every surface that gate covers.
 *
 * THIS TIGHTENS ACCESS. Material becomes less visible, never more, which is the
 * safe direction for a consent-required label. Records affected at the time of
 * writing: 4 on dev (1 published), 3 on heratio.org (2 published - Egyptian
 * Boat and Understream Figure), 0 on sasa.
 *
 * Only the seeded default is changed. An operator who has since set a different
 * condition deliberately keeps it.
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
        if (! Schema::hasTable('icip_tk_label_type')) {
            return;
        }

        $updated = DB::table('icip_tk_label_type')
            ->where('code', 'bc_cb')
            // Only the value the TK migration seeded; a deliberate operator
            // choice since then is left alone.
            ->where('default_access_condition', 'attribution')
            ->update(['default_access_condition' => 'restricted']);

        if ($updated) {
            $affected = Schema::hasTable('icip_tk_label')
                ? DB::table('icip_tk_label as il')
                    ->join('icip_tk_label_type as t', 't.id', '=', 'il.label_type_id')
                    ->where('t.code', 'bc_cb')
                    ->distinct()
                    ->count('il.information_object_id')
                : 0;
            Log::info("#1464: BC Consent Before is now restricted; {$affected} object(s) carry it and are withheld from guests.");
        }
    }

    public function down(): void
    {
        // Deliberately not reverted: loosening a cultural access restriction
        // must be an explicit act, not a rollback side effect.
    }
};
