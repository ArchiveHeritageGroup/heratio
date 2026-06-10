<?php

/**
 * RetentionProposal - Eloquent model for ahg_retention_proposal.
 *
 * heratio#1199 (compliance autopilot) - one auto-drafted retention-schedule
 * proposal per data category surfaced by the PII catalogue scan, held for a
 * data-protection officer to accept. Jurisdiction-neutral; the per-market
 * module owns the concrete law.
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

class RetentionProposal extends Model
{
    protected $table = 'ahg_retention_proposal';

    protected $guarded = ['id'];

    protected $casts = [
        'records_affected' => 'int',
        'accepted_at'      => 'datetime',
        'accepted_by'      => 'int',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
}
