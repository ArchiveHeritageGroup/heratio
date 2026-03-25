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

    // Publication status (taxonomy_id=60 "Publication Status")
    const PUBLICATION_STATUS_DRAFT_ID = 159;
    const PUBLICATION_STATUS_PUBLISHED_ID = 160;

    // Status types (taxonomy_id=59 "Status Types")
    const STATUS_TYPE_PUBLICATION_ID = 158;

    // Job statuses
    const JOB_STATUS_IN_PROGRESS_ID = 183;
    const JOB_STATUS_COMPLETED_ID = 184;
    const JOB_STATUS_ERROR_ID = 185;
}
