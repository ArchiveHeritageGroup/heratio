<?php

/**
 * PrivacyReason - controlled vocabulary of redaction reasons (privacy_reason).
 *
 * Issue #1108 - field-level structured redaction.
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyReason extends Model
{
    protected $table = 'privacy_reason';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'requires_review' => 'boolean',
        'requires_legal_review' => 'boolean',
    ];
}
