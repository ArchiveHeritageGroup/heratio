<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class TaxonomyI18n extends Model
{
    protected $table = 'taxonomy_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name', 'note'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
