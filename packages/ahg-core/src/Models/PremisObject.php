<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class PremisObject extends Model
{
    protected $table = 'premis_object';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'information_object_id', 'puid', 'filename',
        'last_modified', 'date_ingested', 'size', 'mime_type',
    ];

    protected $casts = [
        'last_modified' => 'datetime',
        'date_ingested' => 'date',
    ];

    public function informationObject()
    {
        return $this->belongsTo(QubitInformationObject::class, 'information_object_id');
    }
}
