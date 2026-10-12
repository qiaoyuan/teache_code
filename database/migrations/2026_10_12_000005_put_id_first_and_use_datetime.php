<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['parents', 'administrators', 'students', 'exams', 'exam_subjects', 'intentions', 'audit_logs'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite fresh migrations already declare id first and use DATETIME.
            return;
        }

        $timezone = DB::selectOne('SELECT @@SESSION.time_zone AS timezone')->timezone;
        // TIMESTAMP conversion uses the connection timezone. Store the same UTC
        // value in DATETIME, irrespective of the server/session's default zone.
        DB::statement("SET SESSION time_zone = '+00:00'");
        try {
            foreach ($this->tables as $table) {
                $columns = Schema::getColumns($table);
                $changes = [];
                if ($columns[0]['name'] !== 'id') {
                    $changes[] = 'MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST';
                }
                foreach ($columns as $column) {
                    if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at'], true) && $column['type_name'] !== 'datetime') {
                        $nullability = $column['nullable'] ? 'NULL DEFAULT NULL' : 'NOT NULL';
                        $changes[] = 'MODIFY COLUMN `'.$column['name'].'` DATETIME '.$nullability;
                    }
                }
                if ($changes) {
                    DB::statement("ALTER TABLE `$table` ".implode(', ', $changes));
                }
            }
        } finally {
            DB::statement('SET SESSION time_zone = ?', [$timezone]);
        }
    }

    public function down(): void
    {
        // Keep the agreed DATETIME type and column order; converting back to
        // TIMESTAMP could discard dates outside its supported range.
    }
};
