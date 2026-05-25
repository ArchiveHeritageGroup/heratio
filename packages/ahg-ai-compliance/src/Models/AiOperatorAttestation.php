<?php
/**
 * Heratio - automation-bias training attestation per operator (Art. 14(4)(b)).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiOperatorAttestation extends Model
{
    protected $table = 'ai_operator_attestation';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'user_id'     => 'integer',
        'attested_at' => 'datetime',
        'expires_at'  => 'datetime',
        'created_at'  => 'datetime',
    ];
}
