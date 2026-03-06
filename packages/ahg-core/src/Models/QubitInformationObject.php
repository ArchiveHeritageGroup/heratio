<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use AhgCore\Traits\HasNestedSet;

class QubitInformationObject extends QubitObject
{
    use HasI18n;
    use HasNestedSet;

    protected $table = 'information_object';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'identifier',
        'level_of_description_id',
        'collection_type_id',
        'repository_id',
        'parent_id',
        'description_status_id',
        'description_detail_id',
        'description_identifier',
        'source_standard',
        'display_standard_id',
        'lft',
        'rgt',
        'source_culture',
    ];

    public function i18n()
    {
        return $this->hasMany(InformationObjectI18n::class, 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function repository()
    {
        return $this->belongsTo(QubitRepository::class, 'repository_id');
    }

    public function levelOfDescription()
    {
        return $this->belongsTo(QubitTerm::class, 'level_of_description_id');
    }

    public function collectionType()
    {
        return $this->belongsTo(QubitTerm::class, 'collection_type_id');
    }

    public function descriptionStatus()
    {
        return $this->belongsTo(QubitTerm::class, 'description_status_id');
    }

    public function displayStandard()
    {
        return $this->belongsTo(QubitTerm::class, 'display_standard_id');
    }

    /**
     * Get the title for the current culture.
     */
    public function getTitle(string $culture = 'en'): ?string
    {
        return $this->getTranslated('title', $culture);
    }

    /**
     * Root information object ID.
     */
    const ROOT_ID = 1;
}
