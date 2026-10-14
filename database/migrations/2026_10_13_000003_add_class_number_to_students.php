<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedSmallInteger('class_number')->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable()->change();
            $table->string('entry_grade', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('class_number');
            // Keep legacy fields nullable: new records have no entry dates to restore.
        });
    }
};
