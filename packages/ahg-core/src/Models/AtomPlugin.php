<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AtomPlugin extends Model
{
    protected $table = 'atom_plugin';
    public $timestamps = true;

    protected $fillable = [
        'name', 'class_name', 'version', 'description', 'category',
        'is_enabled', 'is_core', 'is_locked', 'load_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_core' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }
}
