<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class RepositoryI18n extends Model
{
    protected $table = 'repository_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'geocultural_context', 'collecting_policies', 'buildings', 'holdings', 'finding_aids', 'opening_times', 'access_conditions', 'disabled_access', 'research_services', 'reproduction_services', 'public_facilities', 'desc_institution_identifier', 'desc_rules', 'desc_sources', 'desc_revision_history'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
