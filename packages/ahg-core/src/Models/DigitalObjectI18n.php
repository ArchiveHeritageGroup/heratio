<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalObjectI18n extends Model
{
    protected $table = 'digital_object_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
