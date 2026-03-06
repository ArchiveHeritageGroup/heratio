<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class ContactInformation extends Model
{
    use HasI18n;

    protected $table = 'contact_information';
    public $timestamps = true;

    protected $fillable = [
        'actor_id', 'contact_type', 'valid_from', 'valid_to', 'primary_contact',
        'contact_person', 'street_address', 'website', 'email', 'telephone',
        'fax', 'postal_code', 'country_code', 'longitude', 'latitude',
        'source_culture', 'serial_number', 'contact_note',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'primary_contact' => 'boolean',
        'longitude' => 'float',
        'latitude' => 'float',
    ];

    public function i18n()
    {
        return $this->hasMany(ContactInformationI18n::class, 'id');
    }

    public function actor()
    {
        return $this->belongsTo(QubitActor::class, 'actor_id');
    }
}
