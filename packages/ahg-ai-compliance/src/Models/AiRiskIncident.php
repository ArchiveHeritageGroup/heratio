<?php
/**
 * Heratio - AI risk incident report (operator-flagged real-world event).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiRiskIncident extends Model
{
    protected $table = 'ai_risk_incident';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'risk_id'          => 'integer',
        'reporter_user_id' => 'integer',
        'inference_log_id' => 'integer',
        'resolved_at'      => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(AiRisk::class, 'risk_id');
    }
}
