<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';
    public $timestamps = false;

    protected $fillable = ['name', 'scope', 'editable', 'deleteable', 'source_culture', 'serial_number'];

    protected $casts = [
        'editable' => 'boolean',
        'deleteable' => 'boolean',
    ];

    public function i18n()
    {
        return $this->hasMany(SettingI18n::class, 'id');
    }

    public function getValue(string $culture = 'en'): ?string
    {
        return $this->i18n()->where('culture', $culture)->first()?->value;
    }
}
