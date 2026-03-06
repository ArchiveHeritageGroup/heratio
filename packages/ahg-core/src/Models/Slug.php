<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Slug extends Model
{
    protected $table = 'slug';
    public $timestamps = false;

    protected $fillable = ['object_id', 'slug', 'serial_number'];

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }
}
