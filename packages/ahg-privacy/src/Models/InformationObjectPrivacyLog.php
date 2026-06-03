<?php

/**
 * InformationObjectPrivacyLog - audit trail for field-level redaction
 * decisions and redacted views (who, when, what field, legal basis).
 *
 * Issue #1108 - field-level structured redaction.
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;

class InformationObjectPrivacyLog extends Model
{
    protected $table = 'information_object_privacy_log';

    public $timestamps = false;

    protected $guarded = ['id'];
}
