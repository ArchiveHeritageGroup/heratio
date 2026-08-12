<?php

/**
 * #1464 - backfill `rights_record` from `extended_rights` and legacy `rights`.
 *
 * `rights_record` is the decided target model: a strict superset of the other
 * two, covering all five PREMIS bases (copyright, licence, statute, donor,
 * policy) with a direct object_id, and it already has a full CRUD service.
 * It has simply never been populated.
 *
 * The two sources are COMPLEMENTARY, not duplicates, and most objects appear in
 * both:
 *
 *   extended_rights - rights statement + CC licence + holder (display-oriented)
 *   rights (AtoM)   - basis + copyright status (PREMIS-oriented), attached
 *                     through the `relation` table rather than an object_id
 *
 * So this writes ONE rights_record per object, taking statement/licence/holder
 * from extended_rights and basis/copyright-status from the legacy row where one
 * exists. Producing two records per object would misrepresent one right as two.
 *
 * NON-DESTRUCTIVE. Nothing is deleted and no read surface changes here:
 * extended_rights remains the display source until the read repoint lands.
 * This migration only makes the target model real so that switch can be a
 * separate, verifiable step. Re-running is safe - objects that already have a
 * rights_record are skipped.
 *
 * Legacy `rights` rows with no `relation` row cannot be placed against an
 * object and are left alone (logged).
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
    /** Rights-basis terms (taxonomy 68) -> rights_record.basis values. */
    private const BASIS_TERMS = [
        170 => 'copyright',
        218 => 'donor',
    ];

    public function up(): void
    {
        foreach (['rights_record', 'extended_rights'] as $required) {
            if (! Schema::hasTable($required)) {
                return;
            }
        }

        $culture = config('app.locale', 'en');
        $created = 0;
        $skipped = 0;

        foreach (DB::table('extended_rights')->orderBy('id')->get() as $er) {
            if (DB::table('rights_record')->where('object_id', $er->object_id)->exists()) {
                $skipped++;

                continue;
            }

            $legacy = $this->legacyRightsFor((int) $er->object_id);

            $basis = $legacy && isset(self::BASIS_TERMS[(int) $legacy->basis_id])
                ? self::BASIS_TERMS[(int) $legacy->basis_id]
                // No legacy assertion: a CC licence is a licence grant, and
                // anything else defaults to copyright, which is what
                // saveRightsRecord() also assumes.
                : ($er->creative_commons_license_id ? 'license' : 'copyright');

            $i18n = Schema::hasTable('extended_rights_i18n')
                ? DB::table('extended_rights_i18n')
                    ->where('extended_rights_id', $er->id)
                    ->where('culture', $culture)
                    ->first()
                : null;

            $recordId = (int) DB::table('rights_record')->insertGetId($this->onlyColumns('rights_record', [
                'object_id' => $er->object_id,
                'basis' => $basis,
                'rights_statement_id' => $er->rights_statement_id ?? null,
                'cc_license_id' => $er->creative_commons_license_id ?? null,
                'copyright_status' => $legacy ? $this->termName((int) $legacy->copyright_status_id) : null,
                'copyright_holder' => $er->rights_holder ?? null,
                // rights_record.copyright_jurisdiction is varchar(10) - an ISO
                // code - where the legacy column is varchar(1024) free text.
                // Only a value that plausibly IS a code is carried across;
                // anything longer is preserved in copyright_note rather than
                // truncated into a meaningless fragment.
                'copyright_jurisdiction' => $this->jurisdictionCode($legacy->copyright_jurisdiction ?? null),
                'copyright_determination_date' => $legacy->copyright_status_date ?? null,
                'copyright_note' => $this->copyrightNote(
                    $i18n->copyright_notice ?? null,
                    $legacy->copyright_jurisdiction ?? null
                ),
                'statute_determination_date' => $legacy->statute_determination_date ?? null,
                // extended_rights dates win; the legacy row supplies them only
                // where it has no counterpart there.
                'start_date' => $er->rights_date ?? ($legacy->start_date ?? null),
                'end_date' => $er->expiry_date ?? ($legacy->end_date ?? null),
                'created_by' => $er->created_by ?? null,
                'created_at' => $er->created_at ?? now(),
                'updated_at' => now(),
            ]));

            if (Schema::hasTable('rights_record_i18n') && $i18n) {
                DB::table('rights_record_i18n')->insert($this->onlyColumns('rights_record_i18n', [
                    'id' => $recordId,
                    'culture' => $culture,
                    'rights_note' => $i18n->rights_note ?? null,
                    'restriction_note' => $i18n->usage_conditions ?? null,
                ]));
            }

            $created++;
        }

        $orphans = DB::table('rights as r')
            ->leftJoin('relation as rel', 'rel.object_id', '=', 'r.id')
            ->whereNull('rel.subject_id')
            ->count();

        Log::info("#1464 rights_record backfill: {$created} created, {$skipped} skipped (already present), {$orphans} legacy `rights` row(s) left in place with no object link.");
    }

    public function down(): void
    {
        // Not reverted: rights_record may have been edited after the backfill,
        // and the sources are untouched, so there is nothing to restore.
    }

    /** The legacy AtoM rights row for an object, via the relation table. */
    private function legacyRightsFor(int $objectId): ?object
    {
        if (! Schema::hasTable('rights') || ! Schema::hasTable('relation')) {
            return null;
        }

        return DB::table('rights as r')
            ->join('relation as rel', 'rel.object_id', '=', 'r.id')
            ->where('rel.subject_id', $objectId)
            ->select('r.*')
            ->first();
    }

    /** Carry the jurisdiction only when it fits the target's code column. */
    private function jurisdictionCode(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value !== '' && mb_strlen($value) <= 10) ? $value : null;
    }

    /** Keep a too-long jurisdiction as prose rather than losing it. */
    private function copyrightNote(?string $notice, ?string $jurisdiction): ?string
    {
        $parts = [];
        if (trim((string) $notice) !== '') {
            $parts[] = trim((string) $notice);
        }
        $jurisdiction = trim((string) $jurisdiction);
        if ($jurisdiction !== '' && mb_strlen($jurisdiction) > 10) {
            $parts[] = 'Jurisdiction: '.$jurisdiction;
        }

        return $parts ? implode(' - ', $parts) : null;
    }

    private function termName(?int $termId): ?string
    {
        if (! $termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', config('app.locale', 'en'))
            ->value('name');
    }

    /** Filter to columns that exist, so a lagging install cannot fatal. */
    private function onlyColumns(string $table, array $data): array
    {
        return array_filter(
            $data,
            fn ($col) => Schema::hasColumn($table, $col),
            ARRAY_FILTER_USE_KEY
        );
    }
};
