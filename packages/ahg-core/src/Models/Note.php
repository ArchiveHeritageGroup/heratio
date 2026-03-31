<?php

namespace AhgCore\Models;

use AhgCore\Traits\HasI18n;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasI18n;

    protected $table = 'note';
    public $timestamps = false;

    protected $fillable = ['object_id', 'type_id', 'scope', 'user_id', 'source_culture', 'serial_number'];

    public function i18n()
    {
        return $this->hasMany(NoteI18n::class, 'id');
    }

    public function object()
    {
        return $this->belongsTo(BaseObject::class, 'object_id');
    }

    public function type()
    {
        return $this->belongsTo(Term::class, 'type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
