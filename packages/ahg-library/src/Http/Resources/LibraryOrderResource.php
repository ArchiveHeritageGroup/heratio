<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibraryOrderResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-orders';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'order_number'     => $this->order_number,
            'vendor_name'      => $this->vendor_name,
            'vendor_reference' => $this->vendor_reference,
            'order_type'       => $this->order_type,
            'status'           => $this->status,
            'payment_status'   => $this->payment_status,
            'order_date'       => optional($this->order_date)->toDateString(),
            'expected_date'    => optional($this->expected_date)->toDateString(),
            'received_date'    => optional($this->received_date)->toDateString(),
            'budget_code'      => $this->budget_code,
            'subtotal'         => (float) $this->subtotal,
            'tax'              => (float) $this->tax,
            'shipping'         => (float) $this->shipping,
            'total'            => (float) $this->total,
            'currency'         => $this->currency,
            'invoice_number'   => $this->invoice_number,
            'written_off_reason' => $this->written_off_reason,
            'written_off_by'   => $this->written_off_by,
            'written_off_date' => $this->written_off_date,
            'notes'            => $this->notes,
            'created_at'       => optional($this->created_at)->toIso8601String(),
            'updated_at'       => optional($this->updated_at)->toIso8601String(),
        ];
    }

    protected function relationships(Request $request): array
    {
        return [
            'vendor' => $this->identifier('library-vendors', $this->vendor_id),
            'budget' => $this->whenLoaded('budget', fn () => $this->identifier('library-budgets', $this->budget?->id)),
            'lines'  => $this->whenLoaded('lines', fn () => [
                'data' => $this->lines->map(fn ($l) => [
                    'type' => 'library-order-lines',
                    'id'   => (string) $l->id,
                ])->all(),
            ]),
        ];
    }
}
