<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Administrator extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'auth_version'];

    protected $attributes = ['auth_version' => 0];

    protected function casts(): array
    {
        return ['id' => 'integer', 'password' => 'hashed', 'auth_version' => 'integer'];
    }
}
