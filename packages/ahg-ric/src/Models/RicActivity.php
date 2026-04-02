<?php

namespace AhgRic\Models;

use AhgCore\Models\BaseObject;
use AhgCore\Traits\HasI18n;

class RicActivity extends BaseObject
{
    use HasI18n;

    protected $table = 'ric_activity';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type_id',
        'start_date',
        'end_date',
        'place_id',
        'source_culture',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function i18n()
    {
        return $this->hasMany(RicActivityI18n::class, 'id');
    }

    public function place()
    {
        return $this->belongsTo(RicPlace::class, 'place_id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'id');
    }
}
