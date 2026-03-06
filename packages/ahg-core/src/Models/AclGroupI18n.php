<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AclGroupI18n extends Model
{
    protected $table = 'acl_group_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name', 'description'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
