<?php

/**
 * DpiaLog - Eloquent model for privacy_dpia_log (#1109). Append-only audit
 * trail of every DPIA lifecycle event (create / update / review / signoff /
 * archive) and every auto-screen flag change on a linked ROPA entry.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;

class DpiaLog extends Model
{
    protected $table = 'privacy_dpia_log';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'dpia_id'                => 'int',
        'processing_activity_id' => 'int',
        'user_id'                => 'int',
        'created_at'             => 'datetime',
    ];

    public function dpia()
    {
        return $this->belongsTo(Dpia::class, 'dpia_id');
    }
}
