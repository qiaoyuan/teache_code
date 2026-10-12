<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->constrained('parents');
            $t->string('name', 50);
            $t->string('gender', 12);
            $t->string('school', 160);
            $t->unsignedSmallInteger('entry_year');
            $t->string('entry_grade', 3);
            $t->string('current_grade', 3);
            $t->string('contact_phone', 11);
            $t->datetimes();
            $t->softDeletesDatetime();
            $t->index(['parent_id', 'deleted_at']);
        });
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('students');
            $t->string('title', 100);
            $t->date('exam_date');
            $t->string('grade_at_exam', 3);
            $t->string('school_at_exam', 160);
            $t->text('note')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->datetimes();
            $t->softDeletesDatetime();
            $t->index(['student_id', 'exam_date']);
        });
        Schema::create('exam_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained('exams');
            $t->string('name', 80);
            $t->string('subject_key', 80);
            $t->string('mode', 5);
            $t->decimal('score', 7, 2)->nullable();
            $t->decimal('max_score', 7, 2)->nullable();
            $t->string('grade', 30)->nullable();
            $t->unsignedSmallInteger('position');
            $t->datetimes();
            $t->softDeletesDatetime();
            $t->string('active_subject_key', 80)->storedAs('CASE WHEN deleted_at IS NULL THEN subject_key ELSE NULL END');
            $t->unique(['exam_id', 'active_subject_key']);
        });
        Schema::create('intentions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('students');
            $t->string('school', 160);
            $t->string('school_key', 160);
            $t->string('level', 6);
            $t->text('note')->nullable();
            $t->datetimes();
            $t->softDeletesDatetime();
            $t->string('active_school_key', 160)->storedAs('CASE WHEN deleted_at IS NULL THEN school_key ELSE NULL END');
            $t->unique(['student_id', 'active_school_key']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->constrained('administrators');
            $t->string('action', 40);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->json('details')->nullable();
            $t->dateTime('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'intentions', 'exam_subjects', 'exams', 'students'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
