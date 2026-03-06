<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class QubitObject extends Model
{
    protected $table = 'object';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'class_name',
        'serial_number',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function slug()
    {
        return $this->hasOne(Slug::class, 'object_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'object_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'object_id');
    }

    public function terms()
    {
        return $this->belongsToMany(QubitTerm::class, 'object_term_relation', 'object_id', 'term_id');
    }

    public function statuses()
    {
        return $this->hasMany(Status::class, 'object_id');
    }

    public function otherNames()
    {
        return $this->hasMany(OtherName::class, 'object_id');
    }

    public function digitalObjects()
    {
        return $this->hasMany(QubitDigitalObject::class, 'object_id');
    }

    public function relations()
    {
        return $this->hasMany(QubitRelation::class, 'subject_id');
    }

    public function inverseRelations()
    {
        return $this->hasMany(QubitRelation::class, 'object_id');
    }

    public function events()
    {
        return $this->hasMany(QubitEvent::class, 'object_id');
    }

    /**
     * Get the slug string for URL generation.
     */
    public function getSlugAttribute(): ?string
    {
        return $this->slug()?->first()?->slug;
    }
}
