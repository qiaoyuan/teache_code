<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'admitted_schools')) {
            Schema::table('students', function (Blueprint $table) {
                $table->json('admitted_schools')->nullable();
            });
        }

        // Include soft-deleted students and preserve the original field and timestamps.
        DB::table('students')->whereNull('admitted_schools')->orderBy('id')->chunkById(200, function ($students) {
            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->whereNull('admitted_schools')->update([
                    'admitted_schools' => json_encode(
                        $student->admitted_school ? [$student->admitted_school] : [],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('admitted_schools');
        });
    }
};
