<?php

namespace App\Services\Admin;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Intention;
use App\Models\ParentAccount;
use App\Models\Student;
use Illuminate\Http\Request;

class Queries
{
    public function filters(Request $r): array
    {
        return $r->validate(['phone' => 'nullable|string|max:11', 'school' => 'nullable|string|max:160', 'grade' => 'nullable|in:P1,P2,P3,P4,P5,P6,J1,J2,J3,S1,S2,S3', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
    }

    private function like(string $s): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s).'%';
    }

    private function textFilter($q, string $column, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $q->whereRaw("$column LIKE ? ESCAPE '!'", [$this->like($value)]);
        }
    }

    private function current($q, array $f): void
    {
        $this->textFilter($q, 'school', $f['school'] ?? null);
        if (! empty($f['grade'])) {
            $q->where('current_grade', $f['grade']);
        }
    }

    private function phone($q, array $f): void
    {
        if (! empty($f['phone'])) {
            $q->whereHas('parent', fn ($p) => $this->textFilter($p, 'phone', $f['phone']));
        }
    }

    public function students(array $f)
    {
        $q = Student::with('parent')->withCount(['exams', 'intentions']);
        $this->current($q, $f);
        $this->phone($q, $f);

        return $q->orderByDesc('created_at')->orderBy('id');
    }

    public function exams(array $f)
    {
        $q = Exam::whereHas('student')->with(['student.parent', 'subjects']);
        $this->textFilter($q, 'school_at_exam', $f['school'] ?? null);
        if (! empty($f['grade'])) {
            $q->where('grade_at_exam', $f['grade']);
        } if (! empty($f['phone'])) {
            $q->whereHas('student', fn ($s) => $this->phone($s, $f));
        }

        return $q->orderByDesc('exam_date')->orderBy('id');
    }

    public function scores(array $f)
    {
        $q = ExamSubject::visible()->with('exam.student.parent');
        $q->whereHas('exam', function ($e) use ($f) {
            $this->textFilter($e, 'school_at_exam', $f['school'] ?? null);
            if (! empty($f['grade'])) {
                $e->where('grade_at_exam', $f['grade']);
            }if (! empty($f['phone'])) {
                $e->whereHas('student', fn ($s) => $this->phone($s, $f));
            }
        });

        return $q->orderByDesc('created_at')->orderBy('id');
    }

    public function intentions(array $f)
    {
        $q = Intention::visible()->with('student.parent');
        $q->whereHas('student', function ($s) use ($f) {
            $this->current($s, $f);
            $this->phone($s, $f);
        });

        return $q->orderByDesc('created_at')->orderBy('id');
    }

    public function parents(array $f)
    {
        $q = ParentAccount::withCount('students');
        $this->textFilter($q, 'phone', $f['phone'] ?? null);
        if (! empty($f['school']) || ! empty($f['grade'])) {
            $q->whereHas('students', fn ($s) => $this->current($s, $f));
        }

        return $q->orderByDesc('created_at')->orderBy('id');
    }
}
