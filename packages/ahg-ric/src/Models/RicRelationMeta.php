<?php

namespace AhgRic\Models;

use AhgCore\Models\Relation;
use Illuminate\Database\Eloquent\Model;

class RicRelationMeta extends Model
{
    protected $table = 'ric_relation_meta';

    protected $primaryKey = 'relation_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'relation_id',
        'rico_predicate',
        'inverse_predicate',
        'domain_class',
        'range_class',
        'dropdown_code',
        'certainty',
        'evidence',
    ];

    public function relation()
    {
        return $this->belongsTo(Relation::class, 'relation_id', 'id');
    }
}
