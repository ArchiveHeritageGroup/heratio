<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class Relation extends Model
{
    use HasI18n;

    protected $table = 'relation';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'subject_id', 'object_id', 'type_id',
        'start_date', 'end_date', 'source_culture',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function i18n()
    {
        return $this->hasMany(RelationI18n::class, 'id');
    }

    public function subject()
    {
        return $this->belongsTo(BaseObject::class, 'subject_id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'object_id');
    }

    public function type()
    {
        return $this->belongsTo(Term::class, 'type_id');
    }
}
