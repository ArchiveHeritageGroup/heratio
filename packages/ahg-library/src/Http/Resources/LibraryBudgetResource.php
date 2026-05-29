<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibraryBudgetResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-budgets';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'budget_code'      => $this->budget_code,
            'fund_name'        => $this->fund_name,
            'fiscal_year'      => $this->fiscal_year,
            'allocated_amount' => (float) $this->allocated_amount,
            'committed_amount' => (float) $this->committed_amount,
            'spent_amount'     => (float) $this->spent_amount,
            'available_amount' => (float) $this->available_amount,
            'currency'         => $this->currency,
            'category'         => $this->category,
            'department'       => $this->department,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'created_at'       => optional($this->created_at)->toIso8601String(),
            'updated_at'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
