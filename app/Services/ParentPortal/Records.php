<?php

namespace App\Services\ParentPortal;

use App\Models\Exam;
use App\Models\Intention;
use App\Models\Student;
use App\Services\Shared\Text;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Records
{
    public function student(int|string $id): Student
    {
        $s = Student::findOrFail($id);
        Gate::authorize('access', $s);

        return $s;
    }

    public function exam(int|string $id): Exam
    {
        $e = Exam::whereHas('student')->with('subjects')->findOrFail($id);
        Gate::authorize('access', $e->student);

        return $e;
    }

    public function intention(int|string $id): Intention
    {
        $i = Intention::visible()->findOrFail($id);
        Gate::authorize('access', $i->student);

        return $i;
    }

    public function saveExam(Student $student, array $data, ?Exam $exam = null): Exam
    {
        return DB::transaction(function () use ($student, $data, $exam) {
            // Serialize all writes/deletes for a student so deleted descendants cannot reappear.
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $subjects = $data['subjects'];
            unset($data['subjects']);
            if ($exam) {
                $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
                if ((int) $data['version'] !== $exam->version) {
                    throw new HttpResponseException(response()->json(['error' => ['code' => 'VERSION_CONFLICT', 'message' => '考试已被其他设备修改，请刷新后重试']], 409));
                }
                unset($data['version']);
                $exam->update([...$data, 'version' => $exam->version + 1]);
                $exam->subjects()->get()->each->delete();
            } else {
                unset($data['version']);
                $exam = $student->exams()->create($data);
            }
            foreach ($subjects as $position => $s) {
                $exam->subjects()->create(['name' => $s['name'], 'subject_key' => Text::key($s['name']), 'mode' => $s['mode'], 'score' => $s['mode'] === 'score' ? $s['score'] : null, 'max_score' => $s['mode'] === 'score' ? $s['max_score'] : null, 'grade' => $s['mode'] === 'grade' ? $s['grade'] : null, 'position' => $position]);
            }

            return $exam->fresh('subjects');
        });
    }

    public function saveIntention(Student $student, array $data, ?Intention $intention = null): Intention
    {
        return DB::transaction(function () use ($student, $data, $intention) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($intention) {
                $intention = Intention::whereKey($intention->id)->lockForUpdate()->firstOrFail();
            }
            $key = Text::key($data['school']);
            $q = $student->intentions()->where('school_key', $key);
            if ($intention) {
                $q->where('id', '!=', $intention->id);
            } abort_if($q->exists(), 409);
            $data['school_key'] = $key;
            if ($intention) {
                $intention->update($data);

                return $intention;
            }

            return $student->intentions()->create($data);
        });
    }

    public function deleteStudent(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $s = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $s->delete();
        });
    }
}
