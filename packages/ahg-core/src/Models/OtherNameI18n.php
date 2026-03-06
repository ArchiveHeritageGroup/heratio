<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class OtherNameI18n extends Model
{
    protected $table = 'other_name_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name', 'note', 'dates'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
