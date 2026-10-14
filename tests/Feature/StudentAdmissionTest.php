<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Services\Admin\ExcelExport;
use App\Services\Shared\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class StudentAdmissionTest extends TestCase
{
    use RefreshDatabase;

    private function login(): ParentAccount
    {
        $parent = ParentAccount::create(['phone' => '13800009701', 'password' => 'Parent123']);
        $this->actingAs($parent, 'parent')->withSession(['parent_auth_version' => $parent->auth_version]);

        return $parent;
    }

    private function profile(array $extra = []): array
    {
        return [...['name' => '录取学校测试', 'gender' => 'unspecified', 'school' => '当前就读学校',
            'current_grade' => 'J3', 'class_number' => 3, 'contact_phone' => '13800009701'], ...$extra];
    }

    public function test_all_nine_admission_options_can_be_saved_and_read(): void
    {
        $this->login();
        foreach (['宜宾市一中', '宜宾市三中', '宜宾市四中', '宜宾市五中', '宜宾市六中', '三江成外k12', '叙州二中巴蜀班', '凉水井中学', '三江一中'] as $school) {
            $id = $this->postJson('/api/v1/app/students', $this->profile(['admitted_schools' => [$school]]))
                ->assertCreated()->assertJsonPath('data.admitted_schools', [$school])->json('data.id');
            $this->getJson('/api/v1/app/students/'.$id)->assertOk()
                ->assertJsonPath('data.admitted_schools', [$school])->assertJsonPath('data.school', '当前就读学校');
        }
        $this->getJson('/api/v1/app/students')->assertOk()->assertJsonCount(9, 'data');
    }

    public function test_admission_is_optional_and_rejects_invalid_lists(): void
    {
        $this->login();
        foreach ([['其他学校'], ['宜宾一中'], ['三江成外K12'], ['宜宾市一中', '宜宾市一中'], [' 宜宾市一中 ', '宜宾市一中'], [''], [null], [123], [['宜宾市一中']], ['school' => '宜宾市一中'], array_fill(0, 10, '宜宾市一中'), '宜宾市一中', 123, true] as $invalid) {
            $this->postJson('/api/v1/app/students', $this->profile(['admitted_schools' => $invalid]))
                ->assertStatus(400)->assertJsonStructure(['error' => ['fields']]);
        }
        $this->assertDatabaseCount('students', 0);
        foreach ([[], ['admitted_schools' => null], ['admitted_schools' => []]] as $empty) {
            $this->postJson('/api/v1/app/students', $this->profile($empty))
                ->assertCreated()->assertJsonPath('data.admitted_schools', []);
        }
        $this->assertSame(3, Student::whereJsonLength('admitted_schools', 0)->count());
    }

    public function test_all_schools_can_be_selected_together_and_null_clears_the_selection(): void
    {
        $this->login();
        $schools = Text::admittedSchools();
        $id = $this->postJson('/api/v1/app/students', $this->profile(['admitted_schools' => $schools]))
            ->assertCreated()->assertJsonPath('data.admitted_schools', $schools)->json('data.id');
        $this->getJson('/api/v1/app/students/'.$id)->assertOk()->assertJsonPath('data.admitted_schools', $schools);
        $this->putJson('/api/v1/app/students/'.$id, $this->profile(['admitted_schools' => null]))
            ->assertOk()->assertJsonPath('data.admitted_schools', []);
        $this->assertSame([], Student::findOrFail($id)->admitted_schools);
    }

    public function test_cached_single_select_clients_cannot_overwrite_multiple_schools(): void
    {
        $parent = $this->login();
        $schools = ['宜宾市一中', '宜宾市三中'];
        $student = $parent->students()->create($this->profile(['admitted_schools' => $schools, 'admitted_school' => '宜宾市一中']));
        $url = '/api/v1/app/students/'.$student->id;
        $this->putJson($url, $this->profile(['admitted_school' => '宜宾市一中']))
            ->assertStatus(400)->assertJsonStructure(['error' => ['fields' => ['admitted_school']]]);
        $this->putJson($url, $this->profile(['admitted_school' => null]))
            ->assertOk()->assertJsonPath('data.admitted_schools', $schools);
        $this->assertSame($schools, $student->fresh()->admitted_schools);
        $this->assertSame('宜宾市一中', $student->fresh()->getRawOriginal('admitted_school'));
    }

    public function test_admission_can_change_clear_and_survive_older_clients_without_affecting_current_school(): void
    {
        $parent = $this->login();
        $student = $parent->students()->create($this->profile());
        $exam = $student->exams()->create(['title' => '原考试', 'exam_date' => '2026-10-01', 'grade_at_exam' => 'J3', 'school_at_exam' => '历史考试学校']);
        $url = '/api/v1/app/students/'.$student->id;
        $this->putJson($url, $this->profile(['admitted_schools' => [' 宜宾市三中 ', '宜宾市一中']]))
            ->assertOk()->assertJsonPath('data.admitted_schools', ['宜宾市三中', '宜宾市一中']);
        $this->putJson($url, $this->profile())->assertOk()->assertJsonPath('data.admitted_schools', ['宜宾市三中', '宜宾市一中']);
        $this->putJson($url, $this->profile(['admitted_schools' => ['叙州二中巴蜀班']]))
            ->assertOk()->assertJsonPath('data.admitted_schools', ['叙州二中巴蜀班']);
        $this->putJson($url, $this->profile(['admitted_schools' => []]))
            ->assertOk()->assertJsonPath('data.admitted_schools', []);
        $this->assertSame('当前就读学校', $student->fresh()->school);
        $this->assertSame('历史考试学校', $exam->fresh()->school_at_exam);
    }

    public function test_admission_keeps_parent_ownership_and_is_visible_to_admin(): void
    {
        $parent = $this->login();
        $student = $parent->students()->create($this->profile(['admitted_schools' => ['凉水井中学', '三江一中']]));
        $other = ParentAccount::create(['phone' => '13800009702', 'password' => 'Parent123']);
        $this->actingAs($other, 'parent')->withSession(['parent_auth_version' => $other->auth_version]);
        $this->putJson('/api/v1/app/students/'.$student->id, $this->profile(['admitted_schools' => ['宜宾市一中']]))->assertForbidden();
        $this->assertSame(['凉水井中学', '三江一中'], $student->fresh()->admitted_schools);
        $admin = Administrator::create(['username' => 'admission_admin', 'password' => 'Admin123']);
        $this->actingAs($admin, 'admin');
        $this->getJson('/api/v1/admin/students')->assertOk()->assertJsonPath('data.0.admitted_schools', ['凉水井中学', '三江一中']);
        $this->getJson('/api/v1/admin/students/'.$student->id)->assertOk()->assertJsonPath('data.admitted_schools', ['凉水井中学', '三江一中']);
        $this->getJson('/api/v1/admin/parents/'.$parent->id)->assertOk()->assertJsonPath('data.students.0.admitted_schools', ['凉水井中学', '三江一中']);
    }

    public function test_admission_is_exported_in_student_workbooks_and_blank_values_remain_blank(): void
    {
        $parent = $this->login();
        $parent->students()->create($this->profile(['name' => 'A学生', 'admitted_schools' => ['三江成外k12', '宜宾市六中']]));
        $parent->students()->create($this->profile(['name' => 'B学生']));
        foreach (['students', 'all'] as $kind) {
            $file = tempnam(sys_get_temp_dir(), 'student-admission-');
            try {
                app(ExcelExport::class)->write($file, $kind, []);
                $reader = new Reader;
                $reader->open($file);
                $rows = [];
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($sheet->getName() === '学生档案') {
                        foreach ($sheet->getRowIterator() as $row) {
                            $rows[] = $row->toArray();
                        }
                    }
                }
                $reader->close();
                $this->assertSame('已被录取学校', $rows[0][9]);
                $admitted = collect(array_slice($rows, 1))->first(fn ($r) => $r[1] === 'A学生');
                $blank = collect(array_slice($rows, 1))->first(fn ($r) => $r[1] === 'B学生');
                $this->assertSame('三江成外k12、宜宾市六中', $admitted[9]);
                $this->assertSame('', $blank[9]);
                $this->assertSame($parent->phone, $admitted[6]);
            } finally {
                unlink($file);
            }
        }
    }
}
