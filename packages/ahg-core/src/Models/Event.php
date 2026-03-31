<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasI18n;

    protected $table = 'event';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'start_date', 'start_time', 'end_date', 'end_time',
        'type_id', 'object_id', 'actor_id', 'source_culture',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function i18n()
    {
        return $this->hasMany(EventI18n::class, 'id');
    }

    public function type()
    {
        return $this->belongsTo(Term::class, 'type_id');
    }

    public function informationObject()
    {
        return $this->belongsTo(InformationObject::class, 'object_id');
    }

    public function actor()
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }

    const CREATION_ID = 111;
    const ACCUMULATION_ID = 113;
    const CONTRIBUTION_ID = 114;
}
