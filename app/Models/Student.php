<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['id' => 'integer', 'parent_id' => 'integer'];
    }

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(ParentAccount::class, 'parent_id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function intentions()
    {
        return $this->hasMany(Intention::class);
    }
}
