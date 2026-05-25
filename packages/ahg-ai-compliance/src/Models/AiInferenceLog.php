<?php
/**
 * Heratio - AI inference log Eloquent model (Article 12 chain row).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiInferenceLog extends Model
{
    protected $table = 'ai_inference_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'seq'                => 'integer',
        'v'                  => 'integer',
        'latency_ms'         => 'integer',
        'tokens_in'          => 'integer',
        'tokens_out'         => 'integer',
        'user_id'            => 'integer',
        'tenant_id'          => 'integer',
        'payload_json'       => 'array',
        'payload_pruned_at'  => 'datetime',
        'ts'                 => 'datetime',
        'created_at'         => 'datetime',
    ];
}
