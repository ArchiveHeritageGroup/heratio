<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use AhgCore\Traits\HasNestedSet;
use Illuminate\Database\Eloquent\Model;

class QubitTerm extends Model
{
    use HasI18n, HasNestedSet;

    protected $table = 'term';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'taxonomy_id', 'code', 'parent_id', 'lft', 'rgt', 'source_culture',
    ];

    public function i18n()
    {
        return $this->hasMany(TermI18n::class, 'id');
    }

    public function taxonomy()
    {
        return $this->belongsTo(QubitTaxonomy::class, 'taxonomy_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getName(string $culture = 'en'): ?string
    {
        return $this->getTranslated('name', $culture);
    }

    const ROOT_ID = 110;

    // Rights basis IDs
    const RIGHT_BASIS_COPYRIGHT_ID = 170;
    const RIGHT_BASIS_LICENSE_ID = 171;
    const RIGHT_BASIS_STATUTE_ID = 172;
}
