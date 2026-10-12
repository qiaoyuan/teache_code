<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', fn (Blueprint $t) => $t->string('gender', 12)->change());
    }

    public function down(): void
    { /* Keep room for the valid 'unspecified' value during rollback. */
    }
};
