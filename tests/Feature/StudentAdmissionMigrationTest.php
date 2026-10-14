<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAdmissionMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // TestCase guards the database name. Historical ID migrations cannot roll back.
        RefreshDatabaseState::$migrated = false;
        $this->artisan('migrate:fresh')->assertExitCode(0);
    }

    public function test_upgrade_preserves_old_values_relations_timestamps_and_deleted_records(): void
    {
        $migration = require database_path('migrations/2026_10_14_000002_add_admitted_schools_to_students.php');
        $migration->down();
        $parent = ParentAccount::create(['phone' => '13800009703', 'password' => 'Parent123']);
        foreach (['宜宾市六中', null, '叙州二中巴蜀班'] as $index => $school) {
            DB::table('students')->insert([
                'parent_id' => $parent->id, 'name' => '升级测试'.$index, 'gender' => 'unspecified',
                'school' => '原就读学校', 'class_number' => 3, 'current_grade' => 'J3',
                'contact_phone' => $parent->phone, 'admitted_school' => $school,
                'created_at' => '2026-09-01 01:02:03', 'updated_at' => '2026-10-01 02:03:04',
                'deleted_at' => $index === 2 ? '2026-10-02 03:04:05' : null,
            ]);
        }
        $before = DB::table('students')->orderBy('id')->get();
        DB::table('exams')->insert([
            'student_id' => $before[0]->id, 'title' => '历史考试', 'exam_date' => '2026-10-01',
            'school_at_exam' => '原考试学校', 'grade_at_exam' => 'J3',
        ]);
        $exams = DB::table('exams')->get()->toJson();

        $migration->up();
        foreach ($before as $old) {
            $current = (array) DB::table('students')->find($old->id);
            $this->assertSame($old->admitted_school ? [$old->admitted_school] : [], json_decode($current['admitted_schools'], true));
            unset($current['admitted_schools']);
            $this->assertSame((array) $old, $current);
        }
        $this->assertSame($exams, DB::table('exams')->get()->toJson());
        $this->assertSame(['宜宾市六中'], Student::findOrFail($before[0]->id)->admitted_schools);

        // A retry must not restore a deliberately cleared selection from the legacy column.
        DB::table('students')->where('id', $before[0]->id)->update(['admitted_schools' => '[]']);
        $migration->up();
        $this->assertSame([], Student::findOrFail($before[0]->id)->admitted_schools);
    }
}
