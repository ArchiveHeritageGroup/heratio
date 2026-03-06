<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class SettingI18n extends Model
{
    protected $table = 'setting_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'value'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
