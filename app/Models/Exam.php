<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['id' => 'integer', 'student_id' => 'integer', 'exam_date' => 'date:Y-m-d', 'version' => 'integer'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subjects()
    {
        return $this->hasMany(ExamSubject::class)->orderBy('position');
    }
}
