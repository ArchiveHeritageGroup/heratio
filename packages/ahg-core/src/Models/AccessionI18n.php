<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AccessionI18n extends Model
{
    protected $table = 'accession_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'appraisal', 'archival_history', 'location_information', 'physical_characteristics', 'processing_notes', 'received_extent_units', 'scope_and_content', 'source_of_acquisition', 'title'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
