<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class GrantedRight extends Model
{
    protected $table = 'granted_right';
    public $timestamps = false;

    protected $fillable = [
        'rights_id', 'act_id', 'restriction', 'start_date', 'end_date', 'notes', 'serial_number',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'restriction' => 'boolean',
    ];

    public function rights()
    {
        return $this->belongsTo(QubitRights::class, 'rights_id');
    }

    public function act()
    {
        return $this->belongsTo(QubitTerm::class, 'act_id');
    }

    /**
     * Return a human-readable restriction label.
     */
    public static function getRestrictionString($restriction): string
    {
        return match ((int) $restriction) {
            1 => 'Allow',
            0 => 'Disallow',
            default => 'Conditional',
        };
    }
}
