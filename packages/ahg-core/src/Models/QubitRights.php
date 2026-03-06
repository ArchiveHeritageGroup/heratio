<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class QubitRights extends Model
{
    use HasI18n;

    protected $table = 'rights';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'start_date', 'end_date', 'basis_id', 'rights_holder_id',
        'copyright_status_id', 'copyright_status_date', 'copyright_jurisdiction',
        'statute_determination_date', 'statute_citation_id', 'source_culture',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'copyright_status_date' => 'date',
        'statute_determination_date' => 'date',
    ];

    public function i18n()
    {
        return $this->hasMany(RightsI18n::class, 'id');
    }

    public function rightsHolder()
    {
        return $this->belongsTo(QubitRightsHolder::class, 'rights_holder_id');
    }

    public function grantedRights()
    {
        return $this->hasMany(GrantedRight::class, 'rights_id');
    }

    public function basis()
    {
        return $this->belongsTo(QubitTerm::class, 'basis_id');
    }
}
