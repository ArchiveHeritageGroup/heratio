<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class InformationObjectI18n extends Model
{
    protected $table = 'information_object_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'title', 'alternate_title', 'edition', 'extent_and_medium', 'archival_history', 'acquisition', 'scope_and_content', 'appraisal', 'accruals', 'arrangement', 'access_conditions', 'reproduction_conditions', 'physical_characteristics', 'finding_aids', 'location_of_originals', 'location_of_copies', 'related_units_of_description', 'institution_responsible_identifier', 'rules', 'sources', 'revision_history'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
