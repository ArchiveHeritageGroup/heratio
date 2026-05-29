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
 * Subscription terms for a serial title (heratio#1092).
 */
class LibrarySerialSubscription extends Model
{
    protected $table = 'library_serial_subscription';

    protected $fillable = [
        'serial_id', 'subscription_start', 'subscription_end', 'subscription_cost',
        'notification_email', 'auto_claim_max', 'notes',
    ];

    protected $casts = [
        'serial_id'          => 'integer',
        'subscription_start' => 'date',
        'subscription_end'   => 'date',
        'subscription_cost'  => 'decimal:2',
        'auto_claim_max'     => 'integer',
    ];

    public function serial(): BelongsTo
    {
        return $this->belongsTo(LibrarySerial::class, 'serial_id');
    }
}
