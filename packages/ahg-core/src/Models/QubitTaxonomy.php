<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class QubitTaxonomy extends Model
{
    use HasI18n;

    protected $table = 'taxonomy';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'usage', 'parent_id', 'source_culture',
    ];

    public function i18n()
    {
        return $this->hasMany(TaxonomyI18n::class, 'id');
    }

    public function terms()
    {
        return $this->hasMany(QubitTerm::class, 'taxonomy_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getName(string $culture = 'en'): ?string
    {
        return $this->getTranslated('name', $culture);
    }

    // Taxonomy ID constants
    const SUBJECT_ID = 35;
    const PLACE_ID = 42;
    const LEVEL_OF_DESCRIPTION_ID = 34;
    const ACTOR_ENTITY_TYPE_ID = 32;
    const NOTE_TYPE_ID = 44;
    const MEDIA_TYPE_ID = 46;
    const DIGITAL_OBJECT_USAGE_ID = 47;
    const EVENT_TYPE_ID = 40;
    const DESCRIPTION_STATUS_ID = 55;
    const DESCRIPTION_DETAIL_LEVEL_ID = 56;
    const RELATION_TYPE_ID = 53;
    const COLLECTION_TYPE_ID = 62;
    const ROOT_ID = 35;
}
