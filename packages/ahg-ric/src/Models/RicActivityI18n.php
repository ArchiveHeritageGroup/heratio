<?php

namespace AhgRic\Models;

use Illuminate\Database\Eloquent\Model;

class RicActivityI18n extends Model
{
    protected $table = 'ric_activity_i18n';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['id', 'culture'];

    public $keyType = 'string';

    protected $fillable = [
        'id',
        'culture',
        'name',
        'description',
        'date_display',
    ];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
