<?php

namespace AhgRic\Models;

use Illuminate\Database\Eloquent\Model;

class RicRuleI18n extends Model
{
    protected $table = 'ric_rule_i18n';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['id', 'culture'];

    public $keyType = 'string';

    protected $fillable = [
        'id',
        'culture',
        'title',
        'description',
        'legislation',
        'sources',
    ];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
