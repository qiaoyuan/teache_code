<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Models\Student;
use App\Services\Admin\ExcelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class StudentClassTest extends TestCase
{
    use RefreshDatabase;

    private function login(): ParentAccount
    {
        $parent = ParentAccount::create(['phone' => '13800009801', 'password' => 'Parent123']);
        $this->actingAs($parent, 'parent')->withSession(['parent_auth_version' => $parent->auth_version]);

        return $parent;
    }

    private function profile(array $extra = []): array
    {
        return [...['name' => '班级测试', 'gender' => 'unspecified', 'school' => '天池区实验学校',
            'current_grade' => 'P4', 'class_number' => '3', 'contact_phone' => '13800009801'], ...$extra];
    }

    private function exam(array $extra = []): array
    {
        return [...['title' => '期中考试', 'exam_date' => '2026-10-01', 'grade_at_exam' => 'P4',
            'subjects' => [['name' => '数学', 'mode' => 'score', 'score' => 88, 'max_score' => 100]]], ...$extra];
    }

    public function test_profile_only_requires_school_and_class_without_entry_fields(): void
    {
        $this->login();
        $student = $this->postJson('/api/v1/app/students', $this->profile(['school' => ' 天池区实验学校 ', 'class_number' => 3]))
            ->assertCreated()->assertJsonPath('data.class_number', 3)->assertJsonPath('data.school', '天池区实验学校')->json('data');
        $this->assertNull(Student::find($student['id'])->entry_year);
        $this->assertNull(Student::find($student['id'])->entry_grade);
        $this->getJson('/api/v1/app/students/'.$student['id'])->assertOk()->assertJsonPath('data.class_number', 3);
    }

    public function test_class_number_accepts_only_ascii_digits(): void
    {
        $this->login();
        foreach ([0, 100, 900, 99.5, '', '三', '３', '3班', '-1', '1.5', '1e2', '+3', '12345678901', null, 1.5, []] as $invalid) {
            $this->postJson('/api/v1/app/students', $this->profile(['class_number' => $invalid]))
                ->assertStatus(400)->assertJsonStructure(['error' => ['fields' => ['class_number']]]);
        }
        $this->assertDatabaseCount('students', 0);
        foreach ([1, 12, 99] as $valid) {
            $this->postJson('/api/v1/app/students', $this->profile(['class_number' => $valid]))
                ->assertCreated()->assertJsonPath('data.class_number', $valid);
        }
    }

    public function test_editing_old_profile_preserves_legacy_entry_fields(): void
    {
        $parent = $this->login();
        $student = $parent->students()->create($this->profile(['entry_year' => 2022, 'entry_grade' => 'P1', 'class_number' => null]));
        $this->putJson('/api/v1/app/students/'.$student->id, $this->profile(['class_number' => '5']))
            ->assertOk()->assertJsonPath('data.class_number', 5);
        $this->assertEquals(2022, $student->fresh()->entry_year);
        $this->assertSame('P1', $student->fresh()->entry_grade);
    }

    public function test_exam_uses_profile_school_ignores_client_school_and_preserves_history(): void
    {
        $parent = $this->login();
        $student = $parent->students()->create($this->profile());
        $url = '/api/v1/app/students/'.$student->id.'/exams';
        $exam = $this->postJson($url, $this->exam())->assertCreated()->assertJsonPath('data.school_at_exam', '天池区实验学校')->json('data');
        $this->postJson($url, $this->exam(['school_at_exam' => '伪造学校']))->assertCreated()->assertJsonPath('data.school_at_exam', '天池区实验学校');
        $this->putJson('/api/v1/app/students/'.$student->id, $this->profile(['school' => '新区学校']))->assertOk();
        $this->putJson('/api/v1/app/exams/'.$exam['id'], $this->exam(['version' => 1, 'school_at_exam' => '伪造学校']))
            ->assertOk()->assertJsonPath('data.school_at_exam', '天池区实验学校');
        $this->putJson('/api/v1/app/exams/'.$exam['id'], $this->exam(['version' => 1]))->assertStatus(409);
        $this->postJson($url, $this->exam())->assertCreated()->assertJsonPath('data.school_at_exam', '新区学校');
    }

    public function test_student_export_contains_full_school_and_class_instead_of_entry_fields(): void
    {
        $parent = $this->login();
        $parent->students()->create($this->profile(['class_number' => 3]));
        $file = tempnam(sys_get_temp_dir(), 'student-class-');
        try {
            app(ExcelExport::class)->write($file, 'students', []);
            $reader = new Reader;
            $reader->open($file);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $reader->close();
            $this->assertNotContains('入学年份', $rows[0]);
            $this->assertNotContains('入学年级', $rows[0]);
            $this->assertSame('天池区实验学校', $rows[1][array_search('学校全称', $rows[0], true)]);
            $this->assertSame('四年级3班', $rows[1][array_search('班级', $rows[0], true)]);
        } finally {
            unlink($file);
        }
    }
}
