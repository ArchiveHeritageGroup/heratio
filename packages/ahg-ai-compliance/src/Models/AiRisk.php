<?php
/**
 * Heratio - EU AI Act Article 9 risk register row.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiRisk extends Model
{
    protected $table = 'ai_risk_register';

    protected $guarded = [];

    protected $casts = [
        'last_reviewed_at' => 'datetime',
        'reviewer_user_id' => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function incidents(): HasMany
    {
        return $this->hasMany(AiRiskIncident::class, 'risk_id');
    }
}
