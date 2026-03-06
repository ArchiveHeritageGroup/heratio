<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInformationI18n extends Model
{
    protected $table = 'contact_information_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'contact_type', 'city', 'region', 'note'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
