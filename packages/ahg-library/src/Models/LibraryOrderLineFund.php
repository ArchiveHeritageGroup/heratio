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
 * Multi-fund split portion of an acquisitions order line (#1311).
 *
 * One row per {order_line_id, fund_code} portion. The sum of a line's
 * portion amounts must equal the line_total on library_order_line.
 */
class LibraryOrderLineFund extends Model
{
    protected $table = 'library_order_line_fund';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'order_line_id' => 'integer',
        'amount'        => 'float',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(LibraryOrderLine::class, 'order_line_id');
    }
}
