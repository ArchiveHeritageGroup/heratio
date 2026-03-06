<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class StaticPageI18n extends Model
{
    protected $table = 'static_page_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'title', 'content'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
