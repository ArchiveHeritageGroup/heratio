<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibraryOrderLineResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-order-lines';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'order_id'          => (int) $this->order_id,
            'library_item_id'   => $this->library_item_id !== null ? (int) $this->library_item_id : null,
            'title'             => $this->title,
            'author'            => $this->author,
            'isbn'              => $this->isbn,
            'issn'              => $this->issn,
            'publisher'         => $this->publisher,
            'pub_year'          => $this->pub_year,
            'edition'           => $this->edition,
            'material_type'     => $this->material_type,
            'quantity'          => (int) $this->quantity,
            'unit_price'        => (float) $this->unit_price,
            'discount_percent'  => (float) $this->discount_percent,
            'line_total'        => (float) $this->line_total,
            'quantity_received' => (int) $this->quantity_received,
            'status'            => $this->status,
            'budget_code'       => $this->budget_code,
            'fund_code'         => $this->fund_code,
            'notes'             => $this->notes,
        ];
    }

    protected function relationships(Request $request): array
    {
        return [
            'order' => $this->identifier('library-orders', $this->order_id),
        ];
    }
}
