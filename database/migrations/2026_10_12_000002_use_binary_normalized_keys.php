<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE exam_subjects MODIFY subject_key VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, MODIFY active_subject_key VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN subject_key ELSE NULL END) STORED, MODIFY grade VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        DB::statement('ALTER TABLE intentions MODIFY school_key VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, MODIFY active_school_key VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN school_key ELSE NULL END) STORED');
    }

    public function down(): void
    { /* Comparison remains binary to preserve the uniqueness invariant. */
    }
};
