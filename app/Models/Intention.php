<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Intention extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['id' => 'integer', 'student_id' => 'integer'];
    }

    protected $guarded = ['active_school_key'];

    protected $hidden = ['active_school_key'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeVisible($q)
    {
        return $q->whereHas('student');
    }
}
