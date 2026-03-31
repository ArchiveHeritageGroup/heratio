<?php

namespace AhgCore\Models;

class Donor extends Actor
{
    protected $table = 'donor';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id'];
}
