<?php

namespace AhgCore\Models;

class RightsHolder extends Actor
{
    protected $table = 'rights_holder';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id'];
}
