<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class RelationI18n extends Model
{
    protected $table = 'relation_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'description', 'date'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
