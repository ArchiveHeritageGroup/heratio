<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class QubitDigitalObject extends Model
{
    protected $table = 'digital_object';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id', 'object_id', 'usage_id', 'language', 'mime_type',
        'media_type_id', 'name', 'path', 'sequence', 'byte_size',
        'checksum', 'checksum_type', 'parent_id',
    ];

    public function informationObject()
    {
        return $this->belongsTo(QubitInformationObject::class, 'object_id');
    }

    public function usage()
    {
        return $this->belongsTo(QubitTerm::class, 'usage_id');
    }

    public function mediaType()
    {
        return $this->belongsTo(QubitTerm::class, 'media_type_id');
    }

    public function parentDigitalObject()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function derivatives()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get full path to the digital object file.
     */
    public function getFullPath(string $uploadsDir = '/usr/share/nginx/archive'): string
    {
        return rtrim($uploadsDir, '/') . '/' . ltrim($this->path, '/') . $this->name;
    }
}
