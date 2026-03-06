<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table = 'status';
    public $timestamps = false;

    protected $fillable = ['object_id', 'type_id', 'status_id', 'serial_number'];

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }

    public function type()
    {
        return $this->belongsTo(QubitTerm::class, 'type_id');
    }

    public function statusTerm()
    {
        return $this->belongsTo(QubitTerm::class, 'status_id');
    }
}
