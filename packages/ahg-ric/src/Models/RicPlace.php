<?php

namespace AhgRic\Models;

use AhgCore\Models\BaseObject;
use AhgCore\Traits\HasI18n;

class RicPlace extends BaseObject
{
    use HasI18n;

    protected $table = 'ric_place';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type_id',
        'latitude',
        'longitude',
        'authority_uri',
        'parent_id',
        'term_id',
        'source_culture',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function i18n()
    {
        return $this->hasMany(RicPlaceI18n::class, 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function activities()
    {
        return $this->hasMany(RicActivity::class, 'place_id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'id');
    }
}
