<?php

/**
 * #1139 - "BC Consent Before" returns to a non-restricting condition.
 *
 * v1.154.5xx (migration 2026_08_12_180000) made this label restrict access, on
 * Johan's decision. Reviewing it against what the label actually means, he has
 * now decided the other way: Local Contexts defines BC Consent Before as a
 * notice that consent must be sought BEFORE use - an obligation on the person
 * reusing the material, not a bar on seeing it. Attribution carries the notice
 * without withholding the record.
 *
 * THIS LOOSENS ACCESS. Four records on dev carrying this label (Egyptian Boat,
 * Understream Figure, Engelbrecht Family Bible, and one untitled) return to
 * public view; the same label elsewhere stops withholding too. That is the
 * unsafe direction, which is exactly why this is a separate, explicit migration
 * rather than a `down()` on the one that tightened it - that migration's down()
 * deliberately does nothing, on the principle that loosening a cultural
 * restriction must be an act of intent and never a rollback side effect. This
 * file IS that act of intent.
 *
 * The label and its notice remain attached to every record. Only the access
 * condition changes: the community's consent requirement is still recorded and
 * still displayed, it simply no longer makes the record invisible to guests.
 *
 * Only the value the tightening migration set is changed. An operator who has
 * since chosen a different condition deliberately keeps it.
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
            // Only reverse the specific value 2026_08_12_180000 set. If an
            // operator has since chosen sacred_secret, community_voice or
            // anything else, that is their decision and it stands.
            ->where('default_access_condition', 'restricted')
            ->update(['default_access_condition' => 'attribution']);

        if ($updated) {
            $affected = Schema::hasTable('icip_tk_label')
                ? DB::table('icip_tk_label as il')
                    ->join('icip_tk_label_type as t', 't.id', '=', 'il.label_type_id')
                    ->where('t.code', 'bc_cb')
                    ->distinct()
                    ->count('il.information_object_id')
                : 0;
            Log::info("#1139: BC Consent Before is attribution again; {$affected} object(s) carry it and are visible to guests once more, notice intact.");
        }
    }

    public function down(): void
    {
        // Symmetrically deliberate: re-tightening is also an access decision, so
        // it belongs in its own migration rather than happening on a rollback.
    }
};
