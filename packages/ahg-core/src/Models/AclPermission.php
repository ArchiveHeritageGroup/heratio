<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AclPermission extends Model
{
    protected $table = 'acl_permission';
    public $timestamps = true;

    protected $fillable = [
        'user_id', 'group_id', 'object_id', 'action', 'grant_deny', 'conditional', 'constants', 'serial_number',
    ];

    public function user()
    {
        return $this->belongsTo(QubitUser::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(AclGroup::class, 'group_id');
    }

    public function object()
    {
        return $this->belongsTo(QubitObject::class, 'object_id');
    }

    const GRANT = 1;
    const DENY = 0;
    const INHERIT = -1;
}
