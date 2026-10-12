<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Intention;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Services\Admin\PasswordReset;
use App\Services\Admin\Queries;
use App\Services\Admin\Statistics;
use App\Services\Shared\Analysis;
use Illuminate\Http\Request;

class DataController extends Controller
{
    public function __construct(private Queries $queries) {}

    public function overview()
    {
        return ['parents' => ParentAccount::count(), 'students' => Student::count(), 'exams' => Exam::whereHas('student')->count(), 'subjects' => ExamSubject::visible()->count(), 'intentions' => Intention::visible()->count()];
    }

    public function parents(Request $r)
    {
        $f = $this->queries->filters($r);

        return $this->queries->parents($f)->paginate($f['per_page'] ?? 20);
    }

    public function parentDetail(string $id)
    {
        return ['data' => ParentAccount::with('students')->findOrFail($id)];
    }

    public function students(Request $r)
    {
        $f = $this->queries->filters($r);

        return $this->queries->students($f)->paginate($f['per_page'] ?? 20);
    }

    public function studentDetail(string $id, Analysis $a)
    {
        $s = Student::with(['parent', 'exams.subjects', 'intentions'])->findOrFail($id);
        $rows = ExamSubject::visible()->whereHas('exam', fn ($q) => $q->where('student_id', $id))->with('exam')->get();

        return ['data' => $s, 'subjects' => $a->subjects($rows)];
    }

    public function scores(Request $r)
    {
        $f = $this->queries->filters($r);

        return $this->queries->scores($f)->paginate($f['per_page'] ?? 20);
    }

    public function exams(Request $r)
    {
        $f = $this->queries->filters($r);

        return $this->queries->exams($f)->paginate($f['per_page'] ?? 20);
    }

    public function examDetail(string $id)
    {
        return ['data' => Exam::whereHas('student')->with(['student.parent', 'subjects'])->findOrFail($id)];
    }

    public function intentions(Request $r)
    {
        $f = $this->queries->filters($r);

        return $this->queries->intentions($f)->paginate($f['per_page'] ?? 20);
    }

    public function analysis(Request $r, Statistics $stats)
    {
        return $stats->analysis($this->queries->filters($r));
    }

    public function resetPassword(ResetPasswordRequest $r, string $id, PasswordReset $reset)
    {
        $d = $r->validated();
        $reset->reset($r->user(), $id, $d['password'], $r->ip());

        return ['message' => '临时密码已设置，家长下次登录需修改密码'];
    }

    public function audit(Request $r)
    {
        $f = $this->queries->filters($r);

        return AuditLog::with('admin')->orderByDesc('created_at')->orderBy('id')->paginate($f['per_page'] ?? 20);
    }
}
