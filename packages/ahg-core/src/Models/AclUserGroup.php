<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class AclUserGroup extends Model
{
    protected $table = 'acl_user_group';
    public $timestamps = false;

    protected $fillable = ['user_id', 'group_id', 'serial_number'];

    public function user()
    {
        return $this->belongsTo(QubitUser::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(AclGroup::class, 'group_id');
    }
}
