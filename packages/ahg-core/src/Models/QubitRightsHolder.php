<?php

namespace AhgCore\Models;

class QubitRightsHolder extends QubitActor
{
    protected $table = 'rights_holder';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id'];
}
