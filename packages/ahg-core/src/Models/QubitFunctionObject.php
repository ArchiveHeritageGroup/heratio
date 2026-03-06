<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class QubitFunctionObject extends Model
{
    use HasI18n;

    protected $table = 'function_object';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'type_id', 'description_status_id', 'description_detail_id',
        'description_identifier', 'source_standard', 'source_culture',
    ];

    public function i18n()
    {
        return $this->hasMany(FunctionObjectI18n::class, 'id');
    }

    public function type()
    {
        return $this->belongsTo(QubitTerm::class, 'type_id');
    }
}
