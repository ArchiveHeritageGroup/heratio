<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class PhysicalObject extends Model
{
    use HasI18n;

    protected $table = 'physical_object';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'type_id', 'source_culture'];

    public function i18n()
    {
        return $this->hasMany(PhysicalObjectI18n::class, 'id');
    }

    public function type()
    {
        return $this->belongsTo(Term::class, 'type_id');
    }

    public function informationObjects()
    {
        return $this->belongsToMany(
            InformationObject::class,
            'relation',
            'object_id',
            'subject_id'
        );
    }
}
