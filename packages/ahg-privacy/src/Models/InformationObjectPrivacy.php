<?php

/**
 * InformationObjectPrivacy - one privacy profile per information_object.
 *
 * Issue #1108 - field-level structured redaction.
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InformationObjectPrivacy extends Model
{
    protected $table = 'information_object_privacy';

    protected $guarded = ['id'];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(InformationObjectPrivacyField::class, 'privacy_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(PrivacyReason::class, 'privacy_reason_id');
    }
}
