<?php

/**
 * DsarObject - link row between a DSAR (privacy_dsar) and an archival
 * description in its scope (#1108 deliverable 5). Carries the id of the
 * pre-populated information_object_privacy profile once created.
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

class DsarObject extends Model
{
    protected $table = 'privacy_dsar_object';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'dsar_id'               => 'int',
        'information_object_id' => 'int',
        'privacy_id'            => 'int',
        'created_by'            => 'int',
        'created_at'            => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(InformationObjectPrivacy::class, 'privacy_id');
    }
}
