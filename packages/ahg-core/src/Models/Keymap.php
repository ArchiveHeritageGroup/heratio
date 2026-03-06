<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Keymap extends Model
{
    protected $table = 'keymap';
    public $timestamps = false;

    protected $fillable = ['source_id', 'target_id', 'source_name', 'target_name', 'serial_number'];
}
