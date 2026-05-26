<?php

/**
 * ProcessingActivity - Eloquent model for ahg_processing_activity (GDPR Art 30).
 *
 * Issue #669 Phase 1.
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

class ProcessingActivity extends Model
{
    protected $table = 'ahg_processing_activity';

    protected $guarded = ['id'];

    protected $casts = [
        'categories_of_data'     => 'array',
        'categories_of_subjects' => 'array',
        'recipients'             => 'array',
        'transfers_outside_eea'  => 'bool',
        'is_active'              => 'bool',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public const LAWFUL_BASES = [
        'consent',
        'contract',
        'legal_obligation',
        'vital_interests',
        'public_task',
        'legitimate_interests',
    ];

    public function dpias()
    {
        return $this->hasMany(Dpia::class, 'processing_activity_id');
    }
}
