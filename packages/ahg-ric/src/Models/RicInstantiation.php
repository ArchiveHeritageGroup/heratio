<?php

namespace AhgRic\Models;

use AhgCore\Models\BaseObject;
use AhgCore\Models\DigitalObject;
use AhgCore\Traits\HasI18n;

class RicInstantiation extends BaseObject
{
    use HasI18n;

    protected $table = 'ric_instantiation';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'record_id',
        'carrier_type',
        'mime_type',
        'extent_value',
        'extent_unit',
        'digital_object_id',
        'source_culture',
    ];

    protected $casts = [
        'extent_value' => 'decimal:2',
    ];

    public function i18n()
    {
        return $this->hasMany(RicInstantiationI18n::class, 'id');
    }

    public function record()
    {
        return $this->belongsTo(BaseObject::class, 'record_id');
    }

    public function digitalObject()
    {
        return $this->belongsTo(DigitalObject::class, 'digital_object_id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'id');
    }
}
