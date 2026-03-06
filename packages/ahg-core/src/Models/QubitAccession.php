<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;

class QubitAccession extends QubitObject
{
    use HasI18n;

    protected $table = 'accession';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id', 'acquisition_type_id', 'date', 'identifier',
        'processing_priority_id', 'processing_status_id',
        'resource_type_id', 'source_culture',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function i18n()
    {
        return $this->hasMany(AccessionI18n::class, 'id');
    }

    public function acquisitionType()
    {
        return $this->belongsTo(QubitTerm::class, 'acquisition_type_id');
    }

    public function processingPriority()
    {
        return $this->belongsTo(QubitTerm::class, 'processing_priority_id');
    }

    public function processingStatus()
    {
        return $this->belongsTo(QubitTerm::class, 'processing_status_id');
    }
}
