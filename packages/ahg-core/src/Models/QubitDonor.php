<?php

namespace AhgCore\Models;

class QubitDonor extends QubitActor
{
    protected $table = 'donor';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id'];
}
