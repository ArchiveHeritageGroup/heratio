<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;

    protected $fillable = [
        'table_name', 'record_id', 'action', 'field_name',
        'old_value', 'new_value', 'old_record', 'new_record',
        'user_id', 'username', 'ip_address', 'user_agent',
        'module', 'action_description', 'created_at',
    ];

    protected $casts = [
        'old_record' => 'json',
        'new_record' => 'json',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(QubitUser::class, 'user_id');
    }
}
