<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Administrator extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'password' => 'hashed'];
    }
}
