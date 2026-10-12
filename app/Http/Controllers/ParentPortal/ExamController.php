<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\ExamRequest;
use App\Models\ExamSubject;
use App\Services\ParentPortal\Records;
use App\Services\Shared\Analysis;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(private Records $records) {}

    public function index(Request $r, string $student)
    {
        $r->validate(['page' => 'sometimes|integer|min:1']);

        return $this->records->student($student)->exams()->with('subjects')->orderByDesc('exam_date')->orderBy('id')->paginate(20);
    }

    public function store(ExamRequest $r, string $student)
    {
        return response()->json(['data' => $this->records->saveExam($this->records->student($student), $r->validated())], 201);
    }

    public function show(string $id)
    {
        return ['data' => $this->records->exam($id)];
    }

    public function update(ExamRequest $r, string $id)
    {
        $e = $this->records->exam($id);

        return ['data' => $this->records->saveExam($e->student, $r->validated(), $e)];
    }

    public function destroy(string $id)
    {
        $e = $this->records->exam($id);
        $e->delete();

        return response()->noContent();
    }

    public function analysis(string $student, Analysis $a)
    {
        $s = $this->records->student($student);
        $rows = ExamSubject::visible()->whereHas('exam', fn ($q) => $q->where('student_id', $s->id))->with('exam')->get();

        return ['exam_count' => $s->exams()->count(), 'subject_count' => $rows->count(), 'subjects' => $a->subjects($rows)];
    }
}
