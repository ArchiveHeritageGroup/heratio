<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acquisitions purchase order (heratio#1100).
 */
class LibraryOrder extends Model
{
    protected $table = 'library_order';

    protected $fillable = [
        'order_number', 'vendor_id', 'vendor_reference', 'vendor_name',
        'order_date', 'expected_date', 'received_date', 'status', 'order_type',
        'budget_code', 'subtotal', 'tax', 'shipping', 'total', 'currency',
        'invoice_number', 'invoice_date', 'payment_status', 'shipping_address',
        'notes', 'approved_by', 'approved_date', 'created_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'invoice_date'  => 'date',
        'approved_date' => 'datetime',
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'shipping'      => 'decimal:2',
        'total'         => 'decimal:2',
        'vendor_id'     => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LibraryVendor::class, 'vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LibraryOrderLine::class, 'order_id');
    }

    /** Budget is linked by code, not id. */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(LibraryBudget::class, 'budget_code', 'budget_code');
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('order_type', $type);
    }
}
