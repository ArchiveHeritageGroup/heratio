<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;

class Repository extends Actor
{
    protected $table = 'repository';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'identifier',
        'desc_status_id',
        'desc_detail_id',
        'desc_identifier',
        'upload_limit',
        'source_culture',
    ];

    public function repositoryI18n()
    {
        return $this->hasMany(RepositoryI18n::class, 'id');
    }

    public function informationObjects()
    {
        return $this->hasMany(InformationObject::class, 'repository_id');
    }

    public function descStatus()
    {
        return $this->belongsTo(Term::class, 'desc_status_id');
    }

    const ROOT_ID = 6;
}
