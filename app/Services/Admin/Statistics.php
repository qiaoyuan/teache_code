<?php

namespace App\Services\Admin;

class Statistics
{
    public function __construct(private Queries $queries) {}

    public function analysis(array $filters): array
    {
        $base = $this->queries->scores($filters)->reorder()->withoutEagerLoads();
        $grades = (clone $base)->where('mode', 'grade')->selectRaw('subject_key, grade, COUNT(*) as total')->groupBy('subject_key', 'grade')->get()->groupBy('subject_key');
        $groups = (clone $base)->selectRaw("subject_key, MIN(name) as name, SUM(CASE WHEN mode = 'score' THEN 1 ELSE 0 END) as score_count, SUM(CASE WHEN mode = 'grade' THEN 1 ELSE 0 END) as grade_count, AVG(CASE WHEN mode = 'score' THEN score END) as mean_score, MAX(CASE WHEN mode = 'score' THEN score END) as best_score")->groupBy('subject_key')->orderBy('subject_key')->get();
        $subjects = $groups->map(fn ($g) => ['key' => $g->subject_key, 'name' => $g->name, 'score_count' => (int) $g->score_count, 'grade_count' => (int) $g->grade_count, 'mean_score' => $g->mean_score === null ? null : round((float) $g->mean_score, 2), 'best_score' => $g->best_score === null ? null : (float) $g->best_score, 'unit' => '分', 'points' => [], 'grades' => ($grades->get($g->subject_key) ?? collect())->map(fn ($r) => ['label' => $r->grade, 'count' => (int) $r->total])->values()])->values();
        $intentions = $this->queries->intentions($filters)->reorder()->withoutEagerLoads()->selectRaw("school_key, MIN(school) as school, COUNT(DISTINCT student_id) as students, SUM(CASE WHEN level = 'first' THEN 1 ELSE 0 END) as first_count, SUM(CASE WHEN level = 'backup' THEN 1 ELSE 0 END) as backup_count")->groupBy('school_key')->orderByDesc('students')->orderBy('school_key')->get()->map(fn ($r) => ['key' => $r->school_key, 'school' => $r->school, 'students' => (int) $r->students, 'first' => (int) $r->first_count, 'backup' => (int) $r->backup_count])->values();

        return ['subjects' => $subjects, 'intentions' => $intentions];
    }
}
