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
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Serial title (heratio#1092). Backs the serials JSON:API. The richer
 * domain operations (prediction, claims, coverage) live in LibrarySerialService;
 * this model exists for CRUD + relationship eager-loading via the API.
 */
class LibrarySerial extends Model
{
    protected $table = 'library_serial';

    protected $fillable = [
        'title', 'issn', 'frequency', 'publisher', 'status', 'notes',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(LibrarySerialIssue::class, 'serial_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(LibrarySerialSubscription::class, 'serial_id');
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }
}
