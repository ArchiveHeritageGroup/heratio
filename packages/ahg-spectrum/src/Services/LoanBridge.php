<?php

/*
 * LoanBridge - hands a Spectrum loan procedure (loan_in / loan_out) off to the
 * rich ahg-loan module (#1460 Phase 2, "Loans = ahg-loan").
 *
 * Fully gated: if ahg-loan is not installed (no ahg_loan / ahg_loan_object
 * tables) every method is a clean no-op. Writes to the loan tables DIRECTLY,
 * guarded by Schema::hasTable/hasColumn, so ahg-spectrum carries no hard
 * dependency on the loan package's classes. Idempotent: the ahg_loan is matched
 * by loan_number, so a re-sync updates the same record rather than duplicating.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanBridge
{
    /** True only when the ahg-loan component is present. */
    public function available(): bool
    {
        try {
            return Schema::hasTable('ahg_loan') && Schema::hasTable('ahg_loan_object');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Create/update an ahg_loan record from the object's latest Spectrum loan
     * procedure and link the object to it.
     *
     * @param string $direction 'loan_in' or 'loan_out'
     * @param bool   $createIfMissing create the ahg_loan when none exists (the
     *                                explicit button); false = update an existing
     *                                one only (the automatic on-edit path).
     *
     * @return array{status:string, loan_id?:int, loan_number?:string, created?:bool}
     */
    public function syncObject(int $objectId, string $direction, bool $createIfMissing = false): array
    {
        if (! in_array($direction, ['loan_in', 'loan_out'], true)) {
            return ['status' => 'bad_direction'];
        }
        if (! $this->available()) {
            return ['status' => 'unavailable'];
        }

        $sourceTable = $direction === 'loan_in' ? 'spectrum_loan_in' : 'spectrum_loan_out';
        if (! Schema::hasTable($sourceTable)) {
            return ['status' => 'no_loan'];
        }

        $r = DB::table($sourceTable)
            ->where('object_id', $objectId)
            ->orderByDesc('id')
            ->first();
        if (! $r) {
            return ['status' => 'no_loan'];
        }

        [$loanNumber, $loanData] = $this->mapLoan($direction, $r, $objectId);

        $existing = DB::table('ahg_loan')->where('loan_number', $loanNumber)->first();

        if (! $existing && ! $createIfMissing) {
            return ['status' => 'no_loan_record'];
        }

        if ($existing) {
            DB::table('ahg_loan')->where('id', $existing->id)->update(
                $this->onlyColumns('ahg_loan', array_merge($loanData, ['updated_at' => date('Y-m-d H:i:s')]))
            );
            $loanId = (int) $existing->id;
            $created = false;
        } else {
            $loanId = (int) DB::table('ahg_loan')->insertGetId(
                $this->onlyColumns('ahg_loan', array_merge($loanData, ['created_at' => date('Y-m-d H:i:s')]))
            );
            $created = true;
        }

        // Link the object to the loan (idempotent).
        $linked = DB::table('ahg_loan_object')
            ->where('loan_id', $loanId)
            ->where('information_object_id', $objectId)
            ->exists();
        if (! $linked) {
            DB::table('ahg_loan_object')->insert($this->onlyColumns('ahg_loan_object', [
                'loan_id' => $loanId,
                'information_object_id' => $objectId,
                'object_title' => $this->objectTitle($objectId),
                'object_identifier' => DB::table('information_object')->where('id', $objectId)->value('identifier'),
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }

        return ['status' => 'synced', 'loan_id' => $loanId, 'loan_number' => $loanNumber, 'created' => $created];
    }

    /**
     * @return array{0:string,1:array<string,mixed>} [loan_number, ahg_loan data]
     */
    private function mapLoan(string $direction, object $r, int $objectId): array
    {
        if ($direction === 'loan_out') {
            $loanNumber = (string) ($r->loan_number ?: $r->loan_out_number ?: ('SP-LOANOUT-'.$r->id));

            return [$loanNumber, [
                'loan_number' => $loanNumber,
                'loan_type' => 'out',
                'sector' => 'gallery',
                'partner_institution' => $r->borrower_name ?: ($r->venue_name ?: 'Borrower'),
                'partner_contact_name' => $r->contact_person ?: ($r->borrower_contact ?: null),
                'partner_contact_email' => $r->contact_email ?: null,
                'start_date' => $r->loan_start_date ?: $r->loan_out_date,
                'end_date' => $r->loan_end_date ?: $r->loan_return_date,
                'request_date' => $r->loan_agreement_date ?: $r->created_at,
                'purpose' => $r->loan_purpose ?: 'exhibition',
                'title' => $r->exhibition_title ?: ('Loan out to '.($r->borrower_name ?: 'borrower')),
                'description' => $r->object_description ?: null,
                'notes' => $r->loan_out_note ?: $r->loan_note,
                'status' => 'approved',
            ]];
        }

        $loanNumber = (string) ($r->loan_number ?: $r->loan_in_number ?: ('SP-LOANIN-'.$r->id));

        return [$loanNumber, [
            'loan_number' => $loanNumber,
            'loan_type' => 'in',
            'sector' => 'gallery',
            'partner_institution' => $r->lender_name ?: 'Lender',
            'partner_contact_name' => $r->contact_person ?: ($r->lender_contact ?: null),
            'partner_contact_email' => $r->contact_email ?: null,
            'start_date' => $r->loan_start_date ?: $r->loan_in_date,
            'end_date' => $r->loan_end_date ?: $r->loan_return_date,
            'request_date' => $r->loan_agreement_date ?: $r->created_at,
            'purpose' => $r->loan_purpose ?: 'study',
            'title' => 'Loan in from '.($r->lender_name ?: 'lender'),
            'description' => $r->object_description ?: null,
            'notes' => $r->loan_in_note ?: $r->loan_note,
            'status' => 'approved',
        ]];
    }

    private function objectTitle(int $objectId): ?string
    {
        try {
            return DB::table('information_object_i18n')
                ->where('id', $objectId)
                ->orderByRaw("culture = 'en' DESC")
                ->value('title');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function onlyColumns(string $table, array $data): array
    {
        $out = [];
        foreach ($data as $col => $v) {
            try {
                if (Schema::hasColumn($table, $col)) {
                    $out[$col] = $v;
                }
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }
}
