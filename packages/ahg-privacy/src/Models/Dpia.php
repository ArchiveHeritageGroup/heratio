<?php

/**
 * Dpia - Eloquent model for ahg_dpia (GDPR Article 35 Data Protection Impact
 * Assessment register). Issue #669 Phase 1.
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

class Dpia extends Model
{
    protected $table = 'ahg_dpia';

    protected $guarded = ['id'];

    protected $casts = [
        'dpo_consulted_at' => 'date',
        'completed_at'     => 'date',
        'signed_off_at'    => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_REVIEW    = 'review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED  = 'archived';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_REVIEW,
            self::STATUS_COMPLETED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function processingActivity()
    {
        return $this->belongsTo(ProcessingActivity::class, 'processing_activity_id');
    }
}
