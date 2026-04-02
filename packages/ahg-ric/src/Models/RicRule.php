<?php

namespace AhgRic\Models;

use AhgCore\Models\BaseObject;
use AhgCore\Traits\HasI18n;

class RicRule extends BaseObject
{
    use HasI18n;

    protected $table = 'ric_rule';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type_id',
        'jurisdiction',
        'start_date',
        'end_date',
        'authority_uri',
        'source_culture',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function i18n()
    {
        return $this->hasMany(RicRuleI18n::class, 'id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'id');
    }
}
