<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acquisitions vendor (heratio#1100).
 */
class LibraryVendor extends Model
{
    protected $table = 'library_vendor';

    protected $fillable = [
        'vendor_code', 'name', 'vendor_type', 'account_number', 'contact_name',
        'email', 'phone', 'website', 'address', 'city', 'country', 'currency',
        'san', 'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'  => 'bool',
        'created_by' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(LibraryOrder::class, 'vendor_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('vendor_type', $type);
    }
}
