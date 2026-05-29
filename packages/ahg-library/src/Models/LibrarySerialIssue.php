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
 * Per-issue holding against a serial title (heratio#1092).
 */
class LibrarySerialIssue extends Model
{
    protected $table = 'library_serial_issue';

    protected $fillable = [
        'serial_id', 'volume', 'issue_number', 'issue_date', 'received_at',
        'status', 'binding_id', 'shelf_location', 'bound_at', 'notes',
    ];

    protected $casts = [
        'serial_id'  => 'integer',
        'binding_id' => 'integer',
        'issue_date' => 'date',
        'received_at' => 'date',
        'bound_at'   => 'date',
    ];

    public function serial(): BelongsTo
    {
        return $this->belongsTo(LibrarySerial::class, 'serial_id');
    }
}
