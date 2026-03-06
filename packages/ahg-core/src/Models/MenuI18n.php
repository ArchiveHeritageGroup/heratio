<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class MenuI18n extends Model
{
    protected $table = 'menu_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'label', 'description'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
