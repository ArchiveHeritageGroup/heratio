<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class ObjectTermRelation extends Model
{
    protected $table = 'object_term_relation';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'object_id', 'term_id', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }

    public function term()
    {
        return $this->belongsTo(QubitTerm::class, 'term_id');
    }
}
