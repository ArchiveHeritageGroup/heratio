<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class OtherName extends Model
{
    use HasI18n;

    protected $table = 'other_name';
    public $timestamps = false;

    protected $fillable = ['object_id', 'type_id', 'start_date', 'end_date', 'source_culture', 'serial_number'];

    public function i18n()
    {
        return $this->hasMany(OtherNameI18n::class, 'id');
    }

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }

    public function type()
    {
        return $this->belongsTo(QubitTerm::class, 'type_id');
    }
}
