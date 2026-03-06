<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class RightsI18n extends Model
{
    protected $table = 'rights_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'rights_note', 'copyright_note', 'identifier_value', 'identifier_type', 'identifier_role', 'license_terms', 'license_note', 'statute_jurisdiction', 'statute_note'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
