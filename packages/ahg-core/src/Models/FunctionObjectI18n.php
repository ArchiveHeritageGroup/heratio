<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class FunctionObjectI18n extends Model
{
    protected $table = 'function_object_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'authorized_form_of_name', 'classification', 'dates', 'description', 'history', 'legislation', 'institution_identifier', 'revision_history', 'rules', 'sources'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
