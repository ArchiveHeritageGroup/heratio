<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class NoteI18n extends Model
{
    protected $table = 'note_i18n';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id', 'culture'];
    public $keyType = 'string';

    protected $fillable = ['id', 'culture', 'content'];

    public function getKey()
    {
        return $this->id . ':' . $this->culture;
    }
}
