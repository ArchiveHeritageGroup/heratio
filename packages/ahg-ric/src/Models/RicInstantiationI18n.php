<?php

namespace AhgRic\Models;

use Illuminate\Database\Eloquent\Model;

class RicInstantiationI18n extends Model
{
    protected $table = 'ric_instantiation_i18n';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['id', 'culture'];

    public $keyType = 'string';

    protected $fillable = [
        'id',
        'culture',
        'title',
        'description',
        'technical_characteristics',
        'production_technical_characteristics',
    ];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
