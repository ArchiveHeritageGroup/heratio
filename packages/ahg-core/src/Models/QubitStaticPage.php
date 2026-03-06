<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class QubitStaticPage extends Model
{
    use HasI18n;

    protected $table = 'static_page';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'source_culture'];

    public function i18n()
    {
        return $this->hasMany(StaticPageI18n::class, 'id');
    }
}
