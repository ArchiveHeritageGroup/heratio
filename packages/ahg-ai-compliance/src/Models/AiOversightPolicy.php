<?php
/**
 * Heratio - EU AI Act Article 14 oversight policy per AI service.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiOversightPolicy extends Model
{
    protected $table = 'ai_oversight_policy';

    protected $guarded = [];

    protected $casts = [
        'requires_human_review' => 'boolean',
        'confidence_threshold'  => 'float',
        'dual_review_required'  => 'boolean',
        'halted'                => 'boolean',
        'halted_at'             => 'datetime',
        'halted_by_user_id'     => 'integer',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];
}
