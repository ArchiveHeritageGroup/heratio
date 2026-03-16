<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class ClipboardSaveItem extends Model
{
    protected $table = 'clipboard_save_item';
    public $timestamps = false;

    protected $fillable = ['save_id', 'item_class_name', 'slug'];

    public function clipboardSave()
    {
        return $this->belongsTo(ClipboardSave::class, 'save_id');
    }
}
