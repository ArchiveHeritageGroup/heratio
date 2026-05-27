<?php

/**
 * ProcedureUpdateController - PATCH endpoint for single-field updates
 * on Spectrum procedure rows.
 *
 * The endpoint is deliberately narrow: callers supply a procedure type
 * (matched against a whitelist of spectrum_* tables), a row id, a
 * single field name (matched against a per-procedure whitelist) and a
 * single value. The change is mirrored into spectrum_procedure_history
 * for audit.
 *
 * Issue: #739
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgSpectrum\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcedureUpdateController extends Controller
{
    /**
     * Procedure-type to table mapping. Anything not listed is rejected.
     */
    private const PROCEDURE_TABLES = [
        'acquisition'      => 'spectrum_acquisition',
        'condition_check'  => 'spectrum_condition_check',
        'conservation'     => 'spectrum_conservation',
        'deaccession'      => 'spectrum_deaccession',
        'loan_in'          => 'spectrum_loan_in',
        'loan_out'         => 'spectrum_loan_out',
        'location'         => 'spectrum_location',
        'movement'         => 'spectrum_movement',
        'object_entry'     => 'spectrum_object_entry',
        'object_exit'      => 'spectrum_object_exit',
        'valuation'        => 'spectrum_valuation',
    ];

    /**
     * Per-procedure whitelist of fields the PATCH endpoint may write.
     *
     * Anything sensitive (object_id, created_by, created_at, id) is
     * deliberately omitted. Keep aligned with install.sql columns.
     */
    private const FIELD_WHITELIST = [
        'acquisition'     => ['acquisition_method', 'acquisition_date', 'source', 'value', 'currency', 'note', 'workflow_state'],
        'condition_check' => ['condition_grade', 'check_date', 'inspector', 'note', 'workflow_state'],
        'conservation'    => ['treatment_type', 'start_date', 'end_date', 'conservator', 'cost', 'note', 'workflow_state'],
        'deaccession'     => ['reason', 'deaccession_date', 'authorised_by', 'note', 'workflow_state'],
        'loan_in'         => ['lender', 'start_date', 'end_date', 'purpose', 'note', 'workflow_state'],
        'loan_out'        => ['borrower', 'start_date', 'end_date', 'purpose', 'note', 'workflow_state'],
        'location'        => ['location_code', 'location_name', 'note'],
        'movement'        => ['from_location', 'to_location', 'movement_date', 'reason', 'note', 'workflow_state'],
        'object_entry'    => ['entry_method', 'entry_date', 'depositor', 'note', 'workflow_state'],
        'object_exit'     => ['exit_method', 'exit_date', 'recipient', 'note', 'workflow_state'],
        'valuation'       => ['valuation_date', 'valuation_type', 'valuation_amount', 'valuation_currency', 'valuer_name', 'valuer_organization', 'valuation_note', 'workflow_state'],
    ];

    /**
     * PATCH /spectrum/procedure/{id}
     *
     * Body: JSON {"procedure_type": "<key>", "field": "<col>", "value": <any>}
     * URL  : {id} = row id inside the procedure table
     */
    public function patch(Request $request, int $id): JsonResponse
    {
        $procedureType = (string) $request->input('procedure_type', '');
        $field         = (string) $request->input('field', '');
        $value         = $request->input('value');

        if (!isset(self::PROCEDURE_TABLES[$procedureType])) {
            return response()->json([
                'error' => 'Unknown procedure_type',
                'allowed' => array_keys(self::PROCEDURE_TABLES),
            ], 400);
        }
        $table = self::PROCEDURE_TABLES[$procedureType];

        $whitelist = self::FIELD_WHITELIST[$procedureType] ?? [];
        if (!in_array($field, $whitelist, true)) {
            return response()->json([
                'error'   => 'Field is not writable via PATCH',
                'field'   => $field,
                'allowed' => $whitelist,
            ], 422);
        }

        // Defence in depth: also verify the column exists at runtime.
        if (!Schema::hasColumn($table, $field)) {
            return response()->json([
                'error' => 'Field does not exist on procedure table',
                'field' => $field,
                'table' => $table,
            ], 422);
        }

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) {
            return response()->json([
                'error' => 'Procedure row not found',
                'id'    => $id,
                'table' => $table,
            ], 404);
        }

        $oldValue = $row->{$field} ?? null;

        // Light-touch normalisation: empty string becomes NULL for
        // nullable columns to avoid silent "0000-00-00" date drift.
        if ($value === '') {
            $value = null;
        }

        try {
            DB::table($table)->where('id', $id)->update([$field => $value]);

            // History row - best effort, do not fail the PATCH if the
            // history table is absent on a partial schema install.
            if (Schema::hasTable('spectrum_procedure_history')) {
                DB::table('spectrum_procedure_history')->insert([
                    'object_id'      => (int) ($row->object_id ?? 0),
                    'procedure_type' => $procedureType,
                    'procedure_id'   => $id,
                    'action'         => 'patch:' . $field,
                    'description'    => sprintf(
                        '%s: %s -> %s',
                        $field,
                        $this->stringify($oldValue),
                        $this->stringify($value)
                    ),
                    'user_id'        => Auth::id(),
                    'created_at'     => now(),
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Update failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'        => true,
            'procedure_type' => $procedureType,
            'id'             => $id,
            'field'          => $field,
            'old_value'      => $oldValue,
            'new_value'      => $value,
        ]);
    }

    private function stringify($v): string
    {
        if ($v === null) return 'NULL';
        if (is_scalar($v)) return (string) $v;
        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
