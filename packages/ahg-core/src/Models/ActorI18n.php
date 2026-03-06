<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class ActorI18n extends Model
{
    protected $table = 'actor_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'authorized_form_of_name', 'dates_of_existence', 'history', 'places', 'legal_status', 'functions', 'mandates', 'internal_structures', 'general_context', 'institution_responsible_identifier', 'rules', 'sources', 'revision_history'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
