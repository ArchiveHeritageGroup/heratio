<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AhgSetting extends Model
{
    protected $table = 'ahg_settings';
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'setting_key', 'setting_value', 'setting_type', 'setting_group',
        'description', 'is_sensitive', 'updated_by',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(QubitUser::class, 'updated_by');
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }
}
