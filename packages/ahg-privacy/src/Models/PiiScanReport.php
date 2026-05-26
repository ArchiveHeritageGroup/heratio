<?php

/**
 * PiiScanReport - Eloquent model for ahg_pii_scan_report (issue #669 Phase 1).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * Heratio is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace AhgPrivacy\Models;

use Illuminate\Database\Eloquent\Model;

class PiiScanReport extends Model
{
    protected $table = 'ahg_pii_scan_report';

    protected $guarded = ['id'];

    protected $casts = [
        'hits_by_type'     => 'array',
        'findings'         => 'array',
        'scan_started_at'  => 'datetime',
        'scan_finished_at' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public const STATUS_PENDING       = 'pending';
    public const STATUS_REVIEWED      = 'reviewed';
    public const STATUS_REDACTED      = 'redacted';
    public const STATUS_ACCEPTED_RISK = 'accepted_risk';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REVIEWED,
            self::STATUS_REDACTED,
            self::STATUS_ACCEPTED_RISK,
        ];
    }
}
