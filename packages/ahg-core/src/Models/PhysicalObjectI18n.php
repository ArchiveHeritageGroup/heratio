<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalObjectI18n extends Model
{
    protected $table = 'physical_object_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name', 'description', 'location'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
