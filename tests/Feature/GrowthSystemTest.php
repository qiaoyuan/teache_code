<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Services\Admin\ExcelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class GrowthSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_schema_has_id_first_and_datetime_columns(): void
    {
        foreach (['parents', 'administrators', 'students', 'exams', 'exam_subjects', 'intentions', 'audit_logs'] as $table) {
            $columns = Schema::getColumns($table);
            $this->assertSame('id', $columns[0]['name'], $table.' must have id first');
            foreach ($columns as $column) {
                if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at'], true)) {
                    $this->assertSame('datetime', $column['type_name'], $table.'.'.$column['name']);
                }
            }
        }
    }

    public function test_integer_ids_propagate_through_api_relations_and_reject_malformed_paths(): void
    {
        $parent = $this->parent();
        $admin = $this->admin();
        $this->assertIsInt($parent->id);
        $this->assertIsInt($admin->id);
        $this->signIn($parent);
        $student = $this->postJson('/api/v1/app/students', $this->studentData())->assertCreated()->json('data');
        $this->assertIsInt($student['id']);
        $this->assertSame($parent->id, $student['parent_id']);
        $exam = $this->saveExam(Student::findOrFail($student['id']));
        $this->assertIsInt($exam['id']);
        $this->assertSame($student['id'], $exam['student_id']);
        $this->assertIsInt($exam['subjects'][0]['id']);
        $this->assertSame($exam['id'], $exam['subjects'][0]['exam_id']);
        $intention = $this->postJson('/api/v1/app/students/'.$student['id'].'/intentions', ['school' => '中学', 'level' => 'first'])->assertCreated()->json('data');
        $this->assertIsInt($intention['id']);
        $this->assertSame($student['id'], $intention['student_id']);
        foreach (['1abc', '1e0', '0', '-1'] as $id) {
            $this->getJson('/api/v1/app/students/'.$id)->assertNotFound();
            $this->getJson('/api/v1/app/students/'.$id.'/exams')->assertNotFound();
        }
        $this->deleteJson('/api/v1/app/students/'.$student['id'])->assertNoContent();
        $new = $this->postJson('/api/v1/app/students', $this->studentData())->assertCreated()->json('data');
        $this->assertGreaterThan($student['id'], $new['id']);
    }

    public function test_password_changes_only_revoke_parent_sessions_when_admin_id_overlaps(): void
    {
        $parent = $this->parent();
        $admin = $this->admin();
        $this->assertSame($parent->id, $admin->id);
        foreach (['parent', 'admin'] as $portal) {
            DB::table('sessions')->insert(['id' => $portal.'_session', 'portal' => $portal, 'user_id' => $parent->id, 'payload' => '', 'last_activity' => time()]);
        }
        $this->actingAs($admin, 'admin');
        $this->postJson('/api/v1/admin/parents/'.$parent->id.'/reset-password', ['password' => 'TempPass456', 'password_confirmation' => 'TempPass456'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['portal' => 'parent', 'user_id' => $parent->id]);
        $this->assertDatabaseHas('sessions', ['portal' => 'admin', 'user_id' => $admin->id]);
        $audit = AuditLog::first();
        $this->assertIsInt($audit->id);
        $this->assertSame($admin->id, $audit->admin_id);
        $this->assertSame($parent->id, $audit->target_id);
        $parent->refresh();
        DB::table('sessions')->insert(['id' => 'new_parent_session', 'portal' => 'parent', 'user_id' => $parent->id, 'payload' => '', 'last_activity' => time()]);
        $this->signIn($parent);
        $this->postJson('/api/v1/app/auth/change-password', ['current_password' => 'TempPass456', 'password' => 'NewPass789', 'password_confirmation' => 'NewPass789'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['portal' => 'parent', 'user_id' => $parent->id]);
        $this->assertDatabaseHas('sessions', ['portal' => 'admin', 'user_id' => $admin->id]);
    }

    private function parent(string $phone = '13800000001'): ParentAccount
    {
        return ParentAccount::create(['phone' => $phone, 'password' => 'Parent123']);
    }

    private function admin(): Administrator
    {
        return Administrator::create(['username' => 'test_admin', 'password' => 'Admin123']);
    }

    private function signIn(ParentAccount $p): void
    {
        $this->actingAs($p, 'parent')->withSession(['parent_auth_version' => $p->auth_version]);
    }

    private function studentData(array $extra = []): array
    {
        return [...['name' => '测试学生', 'gender' => 'female', 'school' => '实验小学', 'entry_year' => 2023, 'entry_grade' => 'P1', 'current_grade' => 'P4', 'contact_phone' => '13900000001'], ...$extra];
    }

    private function student(ParentAccount $p, array $extra = []): Student
    {
        return $p->students()->create($this->studentData($extra));
    }

    private function examData(array $subjects = [], array $extra = []): array
    {
        return [...['title' => '期中考试', 'exam_date' => '2026-10-01', 'grade_at_exam' => 'P4', 'school_at_exam' => '实验小学', 'note' => '', 'subjects' => $subjects ?: [['name' => '数学', 'mode' => 'score', 'score' => 90, 'max_score' => 100, 'grade' => null], ['name' => '英语', 'mode' => 'grade', 'score' => null, 'max_score' => null, 'grade' => '优秀']]], ...$extra];
    }

    private function saveExam(Student $s, array $subjects = [], array $extra = []): array
    {
        return $this->postJson('/api/v1/app/students/'.$s->id.'/exams', $this->examData($subjects, $extra))->assertCreated()->json('data');
    }

    public function test_unauthenticated_pages_redirect_and_apis_return_401(): void
    {
        $this->get('/app/')->assertRedirect('/app/login');
        $this->get('/admin/')->assertRedirect('/admin/login');
        $this->get('/app/login')->assertOk();
        $this->get('/app/register')->assertOk();
        $this->getJson('/api/v1/app/students')->assertUnauthorized();
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
    }

    public function test_registration_hashes_password_and_duplicate_phone_conflicts(): void
    {
        $data = ['phone' => '13800000002', 'password' => 'Goodpass123', 'password_confirmation' => 'Goodpass123', 'agreement_accepted' => true, 'agreement_version' => config('agreement.version')];
        $this->postJson('/api/v1/app/auth/register', $data)->assertCreated()->assertJsonMissingPath('user.password');
        $p = ParentAccount::first();
        $this->assertNotSame($data['password'], $p->password);
        $this->assertTrue(Hash::check($data['password'], $p->password));
        $this->postJson('/api/v1/app/auth/register', $data)->assertConflict();
    }

    public function test_cross_parent_crud_is_forbidden_and_missing_is_not_found(): void
    {
        $owner = $this->parent();
        $s = $this->student($owner);
        $this->signIn($owner);
        $e = $this->saveExam($s);
        $i = $s->intentions()->create(['school' => '中学', 'school_key' => '中学', 'level' => 'first']);
        $other = $this->parent('13800000003');
        $this->signIn($other);
        $this->getJson('/api/v1/app/students')->assertJsonCount(0, 'data');
        $base = '/api/v1/app/students/'.$s->id;
        $this->getJson($base)->assertForbidden();
        $this->putJson($base, $this->studentData())->assertForbidden();
        $this->deleteJson($base)->assertForbidden();
        $this->getJson($base.'/analysis')->assertForbidden();
        $this->postJson($base.'/exams', $this->examData())->assertForbidden();
        $this->postJson($base.'/intentions', ['school' => '学校二', 'level' => 'backup'])->assertForbidden();
        $this->getJson('/api/v1/app/exams/'.$e['id'])->assertForbidden();
        $this->putJson('/api/v1/app/exams/'.$e['id'], $this->examData(extra: ['version' => 1]))->assertForbidden();
        $this->deleteJson('/api/v1/app/exams/'.$e['id'])->assertForbidden();
        $this->putJson('/api/v1/app/intentions/'.$i->id, ['school' => '中学', 'level' => 'backup'])->assertForbidden();
        $this->deleteJson('/api/v1/app/intentions/'.$i->id)->assertForbidden();
        $this->getJson('/api/v1/app/students/missing')->assertNotFound();
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
    }

    public function test_score_grade_validation_zero_score_precision_and_normalized_duplicates(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $url = '/api/v1/app/students/'.$s->id.'/exams';
        foreach ([['name' => '数学', 'mode' => 'score', 'score' => 101, 'max_score' => 100], ['name' => '数学', 'mode' => 'score', 'score' => 50.123, 'max_score' => 100], ['name' => '数学', 'mode' => 'score', 'score' => 50, 'max_score' => 0], ['name' => '数学', 'mode' => 'score', 'score' => 50, 'max_score' => 100, 'grade' => 'A'], ['name' => '数学', 'mode' => 'grade', 'grade' => 'A', 'score' => 1], ['name' => '数学', 'mode' => 'grade', 'grade' => '']] as $subject) {
            $this->postJson($url, $this->examData([$subject]))->assertStatus(400);
        }
        $this->postJson($url, $this->examData([['name' => ' Ｍath ', 'mode' => 'score', 'score' => 1, 'max_score' => 100], ['name' => 'math', 'mode' => 'grade', 'grade' => 'A']]))->assertStatus(400);
        $e = $this->saveExam($s, [['name' => '数学', 'mode' => 'score', 'score' => 0, 'max_score' => 100]]);
        $this->assertSame('0.00', $e['subjects'][0]['score']);
    }

    public function test_exam_updates_are_atomic_and_reject_stale_versions(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $e = $this->saveExam($s);
        $url = '/api/v1/app/exams/'.$e['id'];
        $this->putJson($url, $this->examData([['name' => '数学', 'mode' => 'score', 'score' => 95, 'max_score' => 100]], ['version' => 1, 'title' => '改后考试']))->assertOk()->assertJsonPath('data.version', 2)->assertJsonCount(1, 'data.subjects');
        $this->putJson($url, $this->examData(extra: ['version' => 1, 'title' => '旧版本覆盖']))->assertConflict();
        $this->assertSame('改后考试', Exam::first()->title);
        $this->assertSame(1, ExamSubject::count());
    }

    public function test_analysis_normalizes_different_maxima_and_separates_custom_grades(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $this->saveExam($s, [['name' => '数学', 'mode' => 'score', 'score' => 80, 'max_score' => 100], ['name' => '英语', 'mode' => 'grade', 'grade' => '优秀']]);
        $this->saveExam($s, [['name' => '数学', 'mode' => 'score', 'score' => 90, 'max_score' => 120], ['name' => '英语', 'mode' => 'grade', 'grade' => 'A+']]);
        $a = $this->getJson('/api/v1/app/students/'.$s->id.'/analysis')->assertOk()->json();
        $math = collect($a['subjects'])->firstWhere('name', '数学');
        $english = collect($a['subjects'])->firstWhere('name', '英语');
        $this->assertTrue($math['normalized']);
        $this->assertEquals(77.5, $math['mean_rate']);
        $this->assertEquals(80, $math['best_rate']);
        $this->assertEquals([80, 75], array_column($math['points'], 'value'));
        $this->assertNull($english['mean_rate']);
        $this->assertCount(2, $english['grades']);
    }

    public function test_intention_unique_normalization_soft_delete_and_priority(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $url = '/api/v1/app/students/'.$s->id.'/intentions';
        $i = $this->postJson($url, ['school' => ' ＡBC 中学 ', 'level' => 'first'])->assertCreated()->json('data');
        $this->postJson($url, ['school' => 'abc 中学', 'level' => 'backup'])->assertConflict();
        $this->putJson('/api/v1/app/intentions/'.$i['id'], ['school' => 'ABC 中学', 'level' => 'backup'])->assertOk();
        $this->deleteJson('/api/v1/app/intentions/'.$i['id'])->assertNoContent();
        $this->postJson($url, ['school' => 'abc 中学', 'level' => 'first'])->assertCreated();
        $this->postJson($url, ['school' => '其他中学', 'level' => 'first'])->assertCreated();
        $this->postJson($url, ['school' => '其他学校', 'level' => 'exploring'])->assertStatus(400);
    }

    public function test_student_delete_excludes_descendants_from_parent_admin_and_export(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $e = $this->saveExam($s);
        $i = $s->intentions()->create(['school' => '目标学校', 'school_key' => '目标学校', 'level' => 'first']);
        $this->deleteJson('/api/v1/app/students/'.$s->id)->assertNoContent();
        $this->getJson('/api/v1/app/exams/'.$e['id'])->assertNotFound();
        $this->putJson('/api/v1/app/intentions/'.$i->id, ['school' => '新学校', 'level' => 'backup'])->assertNotFound();
        $this->actingAs($this->admin(), 'admin');
        $this->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('students', 0)->assertJsonPath('exams', 0)->assertJsonPath('subjects', 0)->assertJsonPath('intentions', 0);
        $this->getJson('/api/v1/admin/scores')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/admin/intentions')->assertJsonPath('total', 0);
        $this->assertSame(['students' => 0, 'scores' => 0, 'intentions' => 0], $this->export()['counts']);
    }

    public function test_admin_filters_use_historical_exam_and_current_student_fields(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $this->saveExam($s);
        $s->update(['school' => '新学校', 'current_grade' => 'P5']);
        $this->actingAs($this->admin(), 'admin');
        $this->getJson('/api/v1/admin/scores?school=%E5%AE%9E%E9%AA%8C&grade=P4&phone=00001')->assertJsonPath('total', 2);
        $this->getJson('/api/v1/admin/scores?school=%E6%96%B0%E5%AD%A6%E6%A0%A1')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/admin/students?school=%E6%96%B0%E5%AD%A6%E6%A0%A1&grade=P5')->assertJsonPath('total', 1);
        $this->getJson('/api/v1/admin/students?school=%E5%AE%9E%E9%AA%8C')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/admin/scores?school=%25')->assertJsonPath('total', 0);
    }

    public function test_reset_revokes_auth_version_forces_change_and_audits_without_password(): void
    {
        $p = $this->parent();
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $this->postJson('/api/v1/admin/parents/'.$p->id.'/reset-password', ['password' => 'TempPass456', 'password_confirmation' => 'TempPass456'])->assertOk();
        $fresh = $p->fresh();
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(2, $fresh->auth_version);
        $this->assertStringNotContainsString('TempPass456', AuditLog::first()->toJson());
        $this->signIn($fresh);
        $this->withSession(['parent_auth_version' => 1]);
        $this->getJson('/api/v1/app/students')->assertUnauthorized();
        $this->postJson('/api/v1/app/auth/login', ['phone' => $fresh->phone, 'password' => 'TempPass456'])->assertOk();
        $this->getJson('/api/v1/app/auth/me')->assertOk();
        $this->getJson('/api/v1/app/students')->assertForbidden()->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/api/v1/app/auth/change-password', ['current_password' => 'TempPass456', 'password' => 'NewPass789', 'password_confirmation' => 'NewPass789'])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->getJson('/api/v1/app/students')->assertOk();
    }

    public function test_admin_cannot_edit_business_data_and_parent_cannot_reset_passwords(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->actingAs($this->admin(), 'admin');
        $this->putJson('/api/v1/admin/students/'.$s->id, $this->studentData())->assertStatus(405);
        Auth::guard('admin')->logout();
        $this->signIn($p);
        $this->postJson('/api/v1/admin/parents/'.$p->id.'/reset-password', ['password' => 'TestPass123', 'password_confirmation' => 'TestPass123'])->assertUnauthorized();
    }

    public function test_api_errors_hide_internal_details(): void
    {
        Route::get('/api/v1/app/test-failure', fn () => throw new \RuntimeException('internal-only-details'));
        $this->getJson('/api/v1/app/test-failure')->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR')->assertJsonMissingPath('trace')->assertDontSee('internal-only-details');
    }

    public function test_student_default_gender_and_contact_edit_work_on_mysql(): void
    {
        $p = $this->parent();
        $this->signIn($p);
        $data = $this->studentData(['gender' => 'unspecified']);
        $s = $this->postJson('/api/v1/app/students', $data)->assertCreated()->assertJsonPath('data.gender', 'unspecified')->json('data');
        $this->putJson('/api/v1/app/students/'.$s['id'], [...$data, 'contact_phone' => '13700000001'])->assertOk();
        $this->getJson('/api/v1/app/students/'.$s['id'])->assertOk()->assertJsonPath('data.contact_phone', '13700000001');
        $this->assertSame('13800000001', $p->fresh()->phone);
    }

    public function test_binary_keys_and_grade_normalization_are_consistent(): void
    {
        $p = $this->parent();
        $s = $this->student($p);
        $this->signIn($p);
        $url = '/api/v1/app/students/'.$s->id.'/intentions';
        $this->postJson($url, ['school' => 'Lycée', 'level' => 'first'])->assertCreated();
        $this->postJson($url, ['school' => 'Lycee', 'level' => 'backup'])->assertCreated();
        $this->saveExam($s, [['name' => '英语', 'mode' => 'grade', 'grade' => 'ａ+'], ['name' => '数学', 'mode' => 'score', 'score' => 80, 'max_score' => 100]]);
        $this->saveExam($s, [['name' => '英语', 'mode' => 'grade', 'grade' => 'A+'], ['name' => '数学', 'mode' => 'score', 'score' => 90, 'max_score' => 120]]);
        $a = $this->getJson('/api/v1/app/students/'.$s->id.'/analysis')->json();
        $grade = collect($a['subjects'])->firstWhere('name', '英语');
        $this->assertSame([['label' => 'A+', 'count' => 2]], $grade['grades']);
        $this->actingAs($this->admin(), 'admin');
        $stats = $this->getJson('/api/v1/admin/analysis')->assertOk()->json();
        $math = collect($stats['subjects'])->firstWhere('name', '数学');
        $this->assertEquals(77.5, $math['mean_rate']);
        $this->assertCount(2, $stats['intentions']);
    }

    private function export(array $filters = []): array
    {
        $file = tempnam(sys_get_temp_dir(), 'growth_test_');
        try {
            $counts = app(ExcelExport::class)->write($file, 'all', $filters);
            $reader = new Reader;
            $reader->open($file);
            $sheets = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[$sheet->getName()] = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $sheets[$sheet->getName()][] = $row->toArray();
                }
            }$reader->close();
            $zip = new \ZipArchive;
            $zip->open($file);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            return compact('counts', 'sheets', 'xml');
        } finally {
            unlink($file);
        }
    }

    public function test_xlsx_exports_all_pages_preserves_phone_and_never_writes_formula_cells(): void
    {
        $p = $this->parent();
        $this->signIn($p);
        $s = $this->student($p, ['name' => '=HYPERLINK("evil","click")']);
        $this->saveExam($s, [['name' => '数学', 'mode' => 'score', 'score' => 80, 'max_score' => 100]]);
        for ($i = 0; $i < 24; $i++) {
            $this->student($p, ['name' => '学生'.$i]);
        }
        $this->actingAs($this->admin(), 'admin');
        $this->getJson('/api/v1/admin/students?per_page=20')->assertJsonCount(20, 'data')->assertJsonPath('total', 25);
        $e = $this->export(['phone' => '13800000001']);
        $this->assertSame(25, $e['counts']['students']);
        $this->assertCount(26, $e['sheets']['学生档案']);
        $this->assertCount(3, $e['sheets']);
        $row = collect($e['sheets']['学生档案'])->first(fn ($r) => ($r[1] ?? '') === '=HYPERLINK("evil","click")');
        $this->assertSame('13800000001', $row[8]);
        $this->assertStringNotContainsString('<f>', $e['xml']);
        $this->assertEquals(80, $e['sheets']['科目成绩'][1][9]);
    }

    public function test_all_export_groups_each_sheet_by_phone_and_name_across_batches(): void
    {
        $higherPhone = $this->parent('13800000002');
        $lowerPhone = $this->parent('13800000001');
        $groups = [
            [$lowerPhone, '学生A'], [$lowerPhone, '学生B'],
            [$higherPhone, '学生A'], [$higherPhone, '学生B'],
        ];
        $expected = array_fill(0, 4, []);
        // Interleave phones and names, including separate students with the same name.
        for ($i = 0; $i < 504; $i++) {
            $group = [3, 1, 2, 0][$i % 4];
            [$parent, $name] = $groups[$group];
            $student = $this->student($parent, ['name' => $name]);
            $exam = $student->exams()->create([
                'title' => '期中考试', 'exam_date' => '2026-10-01',
                'grade_at_exam' => 'P4', 'school_at_exam' => '实验小学',
            ]);
            $score = $exam->subjects()->create([
                'name' => '数学', 'subject_key' => '数学', 'mode' => 'score',
                'score' => 80, 'max_score' => 100, 'position' => 0,
            ]);
            $intention = $student->intentions()->create([
                'school' => '实验中学', 'school_key' => '实验中学', 'level' => 'first',
            ]);
            foreach (['学生档案' => $student, '科目成绩' => $score, '学校意向' => $intention] as $sheet => $record) {
                $expected[$group][$sheet][] = [$record->id, $parent->phone, $name];
            }
        }

        foreach ([[], ['phone' => $lowerPhone->phone, 'school' => '实验', 'grade' => 'P4', 'page' => 2, 'per_page' => 1]] as $filters) {
            $export = $this->export($filters);
            $count = $filters === [] ? 504 : 252;
            $this->assertSame(['students' => $count, 'scores' => $count, 'intentions' => $count], $export['counts']);
            foreach (['学生档案', '科目成绩', '学校意向'] as $sheet) {
                $rows = array_slice($export['sheets'][$sheet], 1);
                $actual = array_map(fn ($row) => $sheet === '学生档案'
                    ? [$row[0], $row[8], $row[1]]
                    : [$row[0], $row[1], $row[2]], $rows);
                $ordered = array_merge(...array_column($expected, $sheet));
                if ($filters !== []) {
                    $ordered = array_values(array_filter($ordered, fn ($row) => $row[1] === $lowerPhone->phone));
                }
                $this->assertSame($ordered, $actual, $sheet);
            }
            foreach (array_slice($export['sheets']['学生档案'], 1) as $row) {
                $this->assertSame([1, 1], [$row[9], $row[10]]);
            }
        }
    }

    public function test_admin_initialization_is_idempotent(): void
    {
        $this->artisan('admin:init', ['--username' => 'tester', '--password' => 'Original123'])->assertSuccessful();
        $hash = Administrator::first()->password;
        $this->artisan('admin:init', ['--username' => 'tester', '--password' => 'Different123'])->assertSuccessful();
        $this->assertSame($hash, Administrator::first()->password);
        $this->assertSame(1, Administrator::count());
    }

    public function test_invalid_password_login_and_login_rate_limit(): void
    {
        $p = $this->parent();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/app/auth/login', ['phone' => $p->phone, 'password' => 'bad'])->assertUnauthorized();
        }$this->postJson('/api/v1/app/auth/login', ['phone' => $p->phone, 'password' => 'Parent123'])->assertStatus(429);
    }
}
