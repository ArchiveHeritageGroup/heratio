<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class TermI18n extends Model
{
    protected $table = 'term_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'name'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
