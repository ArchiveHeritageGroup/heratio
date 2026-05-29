<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acquisitions budget / fund line (heratio#1100).
 */
class LibraryBudget extends Model
{
    protected $table = 'library_budget';

    protected $fillable = [
        'budget_code', 'fund_name', 'fiscal_year', 'allocated_amount',
        'committed_amount', 'spent_amount', 'currency', 'category',
        'department', 'notes', 'status', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'spent_amount'     => 'decimal:2',
        'created_by'       => 'integer',
    ];

    protected $appends = ['available_amount'];

    /** Orders are linked by budget_code, not id. */
    public function orders(): HasMany
    {
        return $this->hasMany(LibraryOrder::class, 'budget_code', 'budget_code');
    }

    /** Remaining = allocated - committed - spent. */
    protected function availableAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => round(
                (float) $this->allocated_amount
                - (float) $this->committed_amount
                - (float) $this->spent_amount,
                2,
            ),
        );
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeFiscalYear(Builder $q, string $year): Builder
    {
        return $q->where('fiscal_year', $year);
    }
}
