<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasI18n;

    protected $table = 'property';
    public $timestamps = false;

    protected $fillable = ['object_id', 'scope', 'name', 'source_culture', 'serial_number'];

    public function i18n()
    {
        return $this->hasMany(PropertyI18n::class, 'id');
    }

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }
}
