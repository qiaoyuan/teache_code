<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\StudentRequest;
use App\Services\ParentPortal\Records;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private Records $records) {}

    public function index(Request $r)
    {
        return ['data' => $r->user()->students()->withCount(['exams', 'intentions'])->orderBy('created_at')->get()];
    }

    public function store(StudentRequest $r)
    {
        return response()->json(['data' => $r->user()->students()->create($r->safe()->except(['admitted_school']))], 201);
    }

    public function show(string $id)
    {
        return ['data' => $this->records->student($id)->loadCount(['exams', 'intentions'])];
    }

    public function update(StudentRequest $r, string $id)
    {
        $s = $this->records->student($id);
        $s->update($r->safe()->except(['admitted_school']));

        return ['data' => $s];
    }

    public function destroy(string $id)
    {
        $this->records->deleteStudent($this->records->student($id));

        return response()->noContent();
    }
}
