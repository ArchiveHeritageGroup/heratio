<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class QubitJob extends Model
{
    protected $table = 'job';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'name', 'download_path', 'completed_at',
        'user_id', 'object_id', 'status_id', 'output',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(QubitUser::class, 'user_id');
    }

    public function status()
    {
        return $this->belongsTo(QubitTerm::class, 'status_id');
    }
}
