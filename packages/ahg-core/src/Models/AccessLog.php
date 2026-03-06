<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $table = 'access_log';
    public $timestamps = false;

    protected $fillable = ['object_id', 'access_date'];

    protected $casts = [
        'access_date' => 'datetime',
    ];

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }
}
