<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;

class Actor extends BaseObject
{
    use HasI18n;

    protected $table = 'actor';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'corporate_body_identifiers',
        'entity_type_id',
        'description_status_id',
        'description_detail_id',
        'description_identifier',
        'source_standard',
        'parent_id',
        'source_culture',
    ];

    public function i18n()
    {
        return $this->hasMany(ActorI18n::class, 'id');
    }

    public function contactInformation()
    {
        return $this->hasMany(ContactInformation::class, 'actor_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function entityType()
    {
        return $this->belongsTo(Term::class, 'entity_type_id');
    }

    /**
     * Get the base object row.
     */
    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'id');
    }
}
