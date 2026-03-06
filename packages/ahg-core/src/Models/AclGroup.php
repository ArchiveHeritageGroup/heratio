<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class AclGroup extends Model
{
    use HasI18n;

    protected $table = 'acl_group';
    public $timestamps = true;

    protected $fillable = ['parent_id', 'source_culture', 'serial_number'];

    public function i18n()
    {
        return $this->hasMany(AclGroupI18n::class, 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(QubitUser::class, 'acl_user_group', 'group_id', 'user_id');
    }

    public function permissions()
    {
        return $this->hasMany(AclPermission::class, 'group_id');
    }

    public function getName(string $culture = 'en'): ?string
    {
        return $this->getTranslated('name', $culture);
    }

    const ADMINISTRATOR_ID = 100;
    const EDITOR_ID = 101;
    const CONTRIBUTOR_ID = 102;
    const TRANSLATOR_ID = 103;
}
