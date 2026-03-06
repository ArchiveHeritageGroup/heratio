<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Aip extends Model
{
    protected $table = 'aip';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'type_id', 'uuid', 'filename', 'size_on_disk',
        'digital_object_count', 'created_at', 'part_of',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(QubitTerm::class, 'type_id');
    }

    public function partOf()
    {
        return $this->belongsTo(self::class, 'part_of');
    }
}
