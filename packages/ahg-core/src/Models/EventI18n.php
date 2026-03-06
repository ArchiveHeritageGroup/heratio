<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class EventI18n extends Model
{
    protected $table = 'event_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name', 'description', 'date'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
