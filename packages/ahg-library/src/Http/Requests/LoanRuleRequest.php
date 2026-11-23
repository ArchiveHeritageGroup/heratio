<?php

/**
 * LoanRuleRequest - validation for creating and editing a loan rule (#1475)
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Requests;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Services\LibraryCalendarService;
use AhgLibrary\Services\LibraryService;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

class LoanRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! LibraryCalendarService::operatorMayManage((int) $this->input('branch_id', 0))) {
            return false;
        }

        $current = $this->currentRule();

        return $current === null
            || ! isset($current->branch_id)
            || LibraryCalendarService::operatorMayManage((int) $current->branch_id);
    }

    public function rules(): array
    {
        return [
            'branch_id' => 'nullable|integer|min:0',
            'material_type' => 'required|string|max:50',
            'patron_type' => 'nullable|string|max:30',
            'loan_period_days' => 'required|integer|min:0|max:3650',
            'renewal_period_days' => 'nullable|integer|min:0|max:3650',
            'max_renewals' => 'nullable|integer|min:0|max:99',
            'fine_per_day' => 'nullable|numeric|min:0|max:99999999.99',
            'fine_cap' => 'nullable|numeric|min:0|max:99999999.99',
            'grace_period_days' => 'nullable|integer|min:0|max:365',
            'is_loanable' => 'nullable|boolean',
            'notes' => 'nullable|string|max:65535',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->currentRule();
            $branch = (int) $this->input('branch_id', 0);
            $material = (string) $this->input('material_type');
            $patron = (string) ($this->input('patron_type') ?: '*');

            if ($branch > 0 && (int) ($current->branch_id ?? 0) !== $branch
                && ! array_key_exists($branch, LibraryBranch::options())) {
                $v->errors()->add('branch_id', __('Choose a branch from the list.'));
            }
            if ($material !== ($current->material_type ?? null)
                && ! array_key_exists($material, self::materialOptions())) {
                $v->errors()->add('material_type', __('Choose a material type from the list.'));
            }
            if ($patron !== ($current->patron_type ?? null)
                && ! array_key_exists($patron, self::patronOptions())) {
                $v->errors()->add('patron_type', __('Choose a patron type from the list.'));
            }

            $clash = DB::table('library_loan_rule')
                ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                ->where('material_type', $material)
                ->where('patron_type', $patron);
            if (LibraryBranch::available()) {
                $clash->where('branch_id', $branch);
            }
            if ($clash->exists()) {
                $v->errors()->add('material_type', $current !== null
                    ? __('Another rule already covers this branch, material and patron type.')
                    : __('A rule already covers this branch, material and patron type. Use Edit on that rule in the list to change it.'));
            }
        });
    }

    /** Catalogue material types, plus any already in use so old data stays selectable. */
    public static function materialOptions(): array
    {
        $options = app(LibraryService::class)->getFormChoices()['materialTypes'];
        $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;

        foreach (['library_item', 'library_loan_rule'] as $table) {
            if (Schema::hasTable($table)) {
                foreach (DB::table($table)->whereNotNull('material_type')->distinct()->pluck('material_type') as $code) {
                    $options[$code] ??= $code;
                }
            }
        }

        return $options;
    }

    /** '*' plus the patron_type dropdown, plus any type already held by a patron or rule. */
    public static function patronOptions(): array
    {
        $options = ['*' => __('All')] + AhgSettingsService::getDropdownChoices('patron_type', false);

        foreach (['library_patron', 'library_loan_rule'] as $table) {
            if (Schema::hasTable($table)) {
                foreach (DB::table($table)->whereNotNull('patron_type')->distinct()->pluck('patron_type') as $code) {
                    $options[$code] ??= $code;
                }
            }
        }

        return $options;
    }

    private function currentRule(): ?object
    {
        $id = $this->route('id');

        return $id !== null ? DB::table('library_loan_rule')->where('id', (int) $id)->first() : null;
    }
}
