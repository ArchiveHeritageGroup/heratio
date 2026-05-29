<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acquisitions order line (heratio#1100).
 */
class LibraryOrderLine extends Model
{
    protected $table = 'library_order_line';

    protected $fillable = [
        'order_id', 'library_item_id', 'isbn', 'issn', 'title', 'author',
        'publisher', 'pub_year', 'edition', 'material_type', 'quantity',
        'unit_price', 'discount_percent', 'line_total', 'quantity_received',
        'supplier_code', 'format', 'received_date', 'status', 'budget_code',
        'fund_code', 'notes',
    ];

    protected $casts = [
        'order_id'          => 'integer',
        'library_item_id'   => 'integer',
        'quantity'          => 'integer',
        'quantity_received' => 'integer',
        'unit_price'        => 'decimal:2',
        'discount_percent'  => 'decimal:2',
        'line_total'        => 'decimal:2',
        'received_date'     => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LibraryOrder::class, 'order_id');
    }
}
