<?php

/**
 * InformationObjectPrivacyField - per-field redaction decision under a profile.
 *
 * Issue #1108 - field-level structured redaction.
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformationObjectPrivacyField extends Model
{
    protected $table = 'information_object_privacy_field';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function privacy(): BelongsTo
    {
        return $this->belongsTo(InformationObjectPrivacy::class, 'privacy_id');
    }
}
