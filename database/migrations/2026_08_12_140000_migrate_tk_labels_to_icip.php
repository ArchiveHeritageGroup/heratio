<?php

/**
 * #1464 - move TK/BC label assignments onto the ICIP model.
 *
 * There were three assignment tables and two vocabularies for the same thing:
 *
 *   extended_rights_tk_label  15 rows, LIVE, keyed to extended_rights.id,
 *                             vocabulary rights_tk_label (19, codes 'TK-A')
 *   rights_object_tk_label     0 rows, keyed to object_id, community as FREE TEXT
 *   icip_tk_label              0 rows, keyed to information_object_id,
 *                             vocabulary icip_tk_label_type (22, codes 'tk_a')
 *
 * `icip_tk_label` + `icip_tk_label_type` win, and it is not a close call:
 * TermProtocolService (#1388/#1406) reads icip_tk_label and joins
 * icip_tk_label_type.default_access_condition to drive community-protocol
 * enforcement. Labels recorded anywhere else are display-only and take no part
 * in that. icip_tk_label_type is also the Local Contexts catalogue the Hub
 * integration (#1448) binds to, and icip_tk_label carries real provenance -
 * community_id, applied_by, local_contexts_project_id - where
 * rights_object_tk_label degrades the community to free text.
 *
 * Codes map by normalising 'TK-A' -> 'tk_a'. Fourteen of the fifteen map
 * cleanly. The fifteenth is BC-CB "BC Consent Before", which has no counterpart
 * in icip_tk_label_type, so it is added, preserving its Local Contexts URI.
 *
 * ITS ACCESS CONDITION IS DELIBERATELY NON-RESTRICTING ('attribution').
 * The column is NOT NULL, so the choice cannot be deferred in the data.
 * TermProtocolService::RESTRICTED is sacred_secret / restricted / gendered /
 * seasonal / community_voice, so 'attribution' gates nothing - which is
 * exactly what these labels do today. A consent-required label arguably
 * SHOULD be 'restricted', but that would newly gate three records, and
 * deciding how a cultural label governs access belongs to the archive and
 * the source community, not to a data migration. Flagged for a deliberate
 * answer rather than inferred here.
 *
 * Non-destructive: extended_rights_tk_label is left in place. Idempotent:
 * an assignment already present for the same object + label is skipped.
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
    /**
     * Access condition given to a label the ICIP catalogue lacks.
     * Non-restricting on purpose: preserves current behaviour and never
     * silently gates material. See the class docblock.
     */
    private const NEW_LABEL_CONDITION = 'attribution';

    public function up(): void
    {
        foreach (['extended_rights_tk_label', 'extended_rights', 'icip_tk_label', 'icip_tk_label_type', 'rights_tk_label'] as $t) {
            if (! Schema::hasTable($t)) {
                return;
            }
        }

        $migrated = 0;
        $skipped = 0;
        $unmapped = [];

        $rows = DB::table('extended_rights_tk_label as ertl')
            ->join('extended_rights as er', 'er.id', '=', 'ertl.extended_rights_id')
            ->leftJoin('rights_tk_label as rtl', 'rtl.id', '=', 'ertl.tk_label_id')
            ->leftJoin('rights_tk_label_i18n as rtli', 'rtli.id', '=', 'rtl.id')
            ->select([
                'ertl.*',
                'er.object_id',
                'rtl.code as legacy_code',
                'rtl.uri as legacy_uri',
                'rtl.category as legacy_category',
                'rtli.name as legacy_name',
            ])
            ->get();

        foreach ($rows as $row) {
            if (! $row->legacy_code || ! $row->object_id) {
                $unmapped[] = $row->id;

                continue;
            }

            $labelTypeId = $this->resolveLabelType($row);
            if (! $labelTypeId) {
                $unmapped[] = $row->legacy_code;

                continue;
            }

            $already = DB::table('icip_tk_label')
                ->where('information_object_id', $row->object_id)
                ->where('label_type_id', $labelTypeId)
                ->exists();
            if ($already) {
                $skipped++;

                continue;
            }

            DB::table('icip_tk_label')->insert($this->onlyColumns('icip_tk_label', [
                'information_object_id' => $row->object_id,
                'label_type_id' => $labelTypeId,
                'community_id' => $row->community_id ?? null,
                'notes' => $row->community_note ?? null,
                'created_by' => null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]));
            $migrated++;
        }

        Log::info(sprintf(
            '#1464 TK label migration: %d assignment(s) moved to icip_tk_label, %d already present, %d unmapped%s',
            $migrated,
            $skipped,
            count($unmapped),
            $unmapped ? ' ('.implode(', ', array_unique($unmapped)).')' : ''
        ));
    }

    public function down(): void
    {
        // Not reverted: the source rows are untouched, so nothing is lost, and
        // removing migrated assignments could strip protocol enforcement from a
        // record an operator has since curated.
    }

    /**
     * Legacy label -> icip_tk_label_type id, normalising 'BC-CB' to 'bc_cb'.
     * A label the catalogue lacks is added rather than dropped.
     */
    private function resolveLabelType(object $row): ?int
    {
        $code = strtolower(str_replace('-', '_', (string) $row->legacy_code));

        $id = DB::table('icip_tk_label_type')->where('code', $code)->value('id');
        if ($id) {
            return (int) $id;
        }

        // Absent from the catalogue: add it so the assignment survives. The
        // access condition is deliberately NON-RESTRICTING - see the docblock.
        return (int) DB::table('icip_tk_label_type')->insertGetId($this->onlyColumns('icip_tk_label_type', [
            'code' => $code,
            // Carry the label's real name over rather than deriving one
            // from the code - the legacy vocabulary already has it.
            'name' => $row->legacy_name ?: $this->titleFromCode($row->legacy_code),
            'category' => $row->legacy_category ?? null,
            'default_access_condition' => self::NEW_LABEL_CONDITION,
            'local_contexts_url' => $row->legacy_uri ?? null,
            'is_local_contexts' => 1,
            'is_active' => 1,
            'created_at' => now(),
        ]));
    }

    /** 'BC-CB' -> 'BC CB'; a human can improve it, but nothing is invented. */
    private function titleFromCode(string $code): string
    {
        return str_replace('-', ' ', strtoupper($code));
    }

    private function onlyColumns(string $table, array $data): array
    {
        return array_filter($data, fn ($c) => Schema::hasColumn($table, $c), ARRAY_FILTER_USE_KEY);
    }
};
