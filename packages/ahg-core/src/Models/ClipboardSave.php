<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class ClipboardSave extends Model
{
    protected $table = 'clipboard_save';
    public $timestamps = false;

    protected $fillable = ['user_id', 'password', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(QubitUser::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(ClipboardSaveItem::class, 'save_id');
    }
}
