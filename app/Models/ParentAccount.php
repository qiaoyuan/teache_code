<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ParentAccount extends Authenticatable
{
    protected $table = 'parents';

    protected $guarded = [];

    protected $hidden = ['password', 'auth_version'];

    protected $attributes = ['must_change_password' => false, 'auth_version' => 1];

    protected function casts(): array
    {
        return ['id' => 'integer', 'password' => 'hashed', 'must_change_password' => 'boolean', 'auth_version' => 'integer'];
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }
}
