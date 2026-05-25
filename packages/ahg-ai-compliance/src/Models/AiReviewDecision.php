<?php
/**
 * Heratio - human-oversight review decision on AI output (Art. 14(4)(d)).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiReviewDecision extends Model
{
    protected $table = 'ai_review_decision';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'inference_log_id'      => 'integer',
        'reviewer_user_id'      => 'integer',
        'countersigner_user_id' => 'integer',
        'countersigned_at'      => 'datetime',
        'created_at'            => 'datetime',
    ];
}
