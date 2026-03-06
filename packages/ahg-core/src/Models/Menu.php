<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';
    public $timestamps = true;

    protected $fillable = [
        'parent_id', 'name', 'path', 'lft', 'rgt', 'source_culture', 'serial_number',
    ];

    public function i18n()
    {
        return $this->hasMany(MenuI18n::class, 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getLabel(string $culture = 'en'): ?string
    {
        return $this->i18n()->where('culture', $culture)->first()?->label;
    }
}
