<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSubject extends Model
{
    use SoftDeletes;

    protected $guarded = ['active_subject_key'];

    protected $hidden = ['active_subject_key'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'exam_id' => 'integer', 'score' => 'decimal:2', 'max_score' => 'decimal:2'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function scopeVisible($q)
    {
        return $q->whereHas('exam', fn ($e) => $e->whereHas('student'));
    }
}
