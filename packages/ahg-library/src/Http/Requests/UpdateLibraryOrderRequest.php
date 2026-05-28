<?php

/**
 * UpdateLibraryOrderRequest - same validation as store
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
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

namespace AhgLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLibraryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ACL handled by route middleware
    }

    public function rules(): array
    {
        $activeStatuses = ['draft', 'submitted', 'approved', 'ordered', 'partial', 'received', 'cancelled'];
        $activeTypes    = ['purchase', 'standing_order', 'gift', 'exchange', 'deposit', 'approval'];

        return [
            'order_number'      => ['nullable', 'string', 'max:50'],
            'order_date'        => ['nullable', 'date'],
            'expected_date'     => ['nullable', 'date', 'after_or_equal:order_date'],
            'vendor_name'       => ['nullable', 'string', 'max:255'],
            'vendor_contact'    => ['nullable', 'string', 'max:255'],
            'vendor_reference'  => ['nullable', 'string', 'max:100'],
            'budget_code'       => ['nullable', 'string', 'max:50'],
            'order_type'        => ['nullable', 'string', Rule::in($activeTypes)],
            'status'            => ['nullable', 'string', Rule::in($activeStatuses)],
            'shipping_cost'     => ['nullable', 'numeric', 'min:0'],
            'handling_cost'     => ['nullable', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'lines'             => ['nullable', 'array'],
            'lines.*.title'     => ['required_with:lines', 'string', 'max:500'],
            'lines.*.isbn'      => ['nullable', 'string', 'max:20'],
            'lines.*.issn'      => ['nullable', 'string', 'max:20'],
            'lines.*.author'   => ['nullable', 'string', 'max:300'],
            'lines.*.publisher' => ['nullable', 'string', 'max:255'],
            'lines.*.pub_year'  => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'lines.*.format'    => ['nullable', 'string', 'max:50'],
            'lines.*.quantity'  => ['nullable', 'integer', 'min:1', 'max:9999'],
            'lines.*.unit_price'=> ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'lines.*.currency'  => ['nullable', 'string', 'max:3'],
            'lines.*.supplier_code'=> ['nullable', 'string', 'max:50'],
            'lines.*.received_qty'=> ['nullable', 'integer', 'min:0'],
            'lines.*.line_notes'=> ['nullable', 'string', 'max:500'],
        ];
    }
}