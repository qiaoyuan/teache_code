<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['parents', 'administrators', 'students', 'exams', 'exam_subjects', 'intentions', 'audit_logs'];

    private array $relations = [
        'students' => ['parent_id' => 'parents'],
        'exams' => ['student_id' => 'students'],
        'exam_subjects' => ['exam_id' => 'exams'],
        'intentions' => ['student_id' => 'students'],
        'audit_logs' => ['admin_id' => 'administrators'],
    ];

    public function up(): void
    {
        // Fresh installations already use integer IDs. Existing UUID installations
        // must be backed up and put into maintenance mode before this migration.
        if (Schema::getColumnType('parents', 'id') !== 'bigint' && Schema::getColumnType('parents', 'id') !== 'integer') {
            if (DB::getDriverName() !== 'mysql') {
                throw new RuntimeException('Existing UUID databases require MySQL for the data-preserving ID migration.');
            }
            if (! app()->isDownForMaintenance()) {
                throw new RuntimeException('Back up the database and run artisan down before converting existing UUID IDs.');
            }
            $this->convertExistingIds();
        }

        if (Schema::getColumnType('sessions', 'user_id') !== 'bigint' && Schema::getColumnType('sessions', 'user_id') !== 'integer') {
            // Serialized authentication payloads still contain the old IDs. Force
            // both portals to sign in again rather than replaying stale sessions.
            DB::table('sessions')->delete();
            Schema::table('sessions', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->change());
        }
        if (! Schema::hasColumn('sessions', 'portal')) {
            DB::table('sessions')->delete();
            Schema::table('sessions', function (Blueprint $t) {
                $t->string('portal', 6);
                $t->index(['portal', 'user_id']);
            });
        }
    }

    private function convertExistingIds(): void
    {
        // Refuse unknown audit targets before changing any schema.
        if (DB::table('audit_logs')->whereNotNull('target_id')->where('action', '!=', 'parent_password_reset')->exists()) {
            throw new RuntimeException('Unknown audit target type: cannot safely map IDs.');
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `$table` ADD COLUMN `numeric_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE KEY `numeric_id` (`numeric_id`)");
        }

        foreach ($this->relations as $table => $columns) {
            foreach ($columns as $column => $parent) {
                DB::statement("ALTER TABLE `$table` ADD COLUMN `numeric_$column` BIGINT UNSIGNED NULL");
                DB::statement("UPDATE `$table` c JOIN `$parent` p ON c.`$column` = p.id SET c.`numeric_$column` = p.numeric_id");
                if (DB::table($table)->whereNull('numeric_'.$column)->exists()) {
                    throw new RuntimeException("Unmapped relation: $table.$column. Restore the pre-migration backup.");
                }
            }
        }
        DB::statement('ALTER TABLE `audit_logs` ADD COLUMN `numeric_target_id` BIGINT UNSIGNED NULL');
        DB::statement('UPDATE audit_logs a JOIN parents p ON a.target_id = p.id SET a.numeric_target_id = p.numeric_id WHERE a.action = \'parent_password_reset\'');
        if (DB::table('audit_logs')->whereNotNull('target_id')->whereNull('numeric_target_id')->exists()) {
            throw new RuntimeException('Unmapped audit target. Restore the pre-migration backup.');
        }

        // All references have been mapped, including soft-deleted rows.
        foreach ($this->relations as $table => $columns) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                $name = $foreign['name'];
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$name`");
            }
        }

        foreach ($this->tables as $table) {
            $changes = ['DROP PRIMARY KEY', 'DROP COLUMN `id`', 'CHANGE COLUMN `numeric_id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD PRIMARY KEY (`id`)', 'DROP INDEX `numeric_id`'];
            foreach (Schema::getIndexes($table) as $index) {
                if (array_intersect($index['columns'], array_keys($this->relations[$table] ?? []))) {
                    $changes[] = 'DROP INDEX `'.$index['name'].'`';
                }
            }
            foreach ($this->relations[$table] ?? [] as $column => $parent) {
                $changes[] = "DROP COLUMN `$column`";
                $changes[] = "CHANGE COLUMN `numeric_$column` `$column` BIGINT UNSIGNED NOT NULL";
            }
            if ($table === 'audit_logs') {
                $changes[] = 'DROP COLUMN `target_id`';
                $changes[] = 'CHANGE COLUMN `numeric_target_id` `target_id` BIGINT UNSIGNED NULL';
            }
            // Explicitly rebuild composite indexes; MySQL can retain the
            // surviving columns and the old index name when a column is dropped.
            $changes = [...$changes, ...match ($table) {
                'students' => ['ADD INDEX `students_parent_id_deleted_at_index` (`parent_id`, `deleted_at`)'],
                'exams' => ['ADD INDEX `exams_student_id_exam_date_index` (`student_id`, `exam_date`)'],
                'exam_subjects' => ['ADD UNIQUE KEY `exam_subjects_exam_id_active_subject_key_unique` (`exam_id`, `active_subject_key`)'],
                'intentions' => ['ADD UNIQUE KEY `intentions_student_id_active_school_key_unique` (`student_id`, `active_school_key`)'],
                default => [],
            }];
            DB::statement("ALTER TABLE `$table` ".implode(', ', $changes));
        }

        foreach ($this->relations as $table => $columns) {
            foreach ($columns as $column => $parent) {
                DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_{$column}_foreign` FOREIGN KEY (`$column`) REFERENCES `$parent` (`id`)");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Integer IDs cannot be converted back to the original UUIDs. Restore the pre-migration database backup to roll back.');
    }
};
