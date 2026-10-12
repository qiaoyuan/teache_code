<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 11)->unique();
            $t->string('password');
            $t->boolean('must_change_password')->default(false);
            $t->unsignedInteger('auth_version')->default(1);
            $t->datetimes();
        });
        Schema::create('administrators', function (Blueprint $t) {
            $t->id();
            $t->string('username', 64)->unique();
            $t->string('password');
            $t->datetimes();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('portal', 6);
            $t->index(['portal', 'user_id']);
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('administrators');
        Schema::dropIfExists('parents');
    }
};
