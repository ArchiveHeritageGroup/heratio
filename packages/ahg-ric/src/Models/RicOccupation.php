<?php

/**
 * RicOccupation — Eloquent model for the rico:Occupation entity.
 *
 * Models a role/profession/position held by an actor over a time-span
 * (ISAAR(CPF) section 5.2.6 + RiC-O semantics).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Models;

use AhgCore\Models\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RicOccupation extends Model
{
    protected $table = 'ric_occupation';

    protected $fillable = [
        'actor_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'is_current',
        'source_culture',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * The actor that holds this occupation.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }
}
