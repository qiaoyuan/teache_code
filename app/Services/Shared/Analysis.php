<?php

namespace App\Services\Shared;

use Illuminate\Support\Collection;

class Analysis
{
    public function subjects(Collection $rows): array
    {
        return $rows->groupBy('subject_key')->map(function ($group, $key) {
            $scores = $group->where('mode', 'score')->sortBy([
                fn ($a, $b) => $a->exam->exam_date <=> $b->exam->exam_date,
                fn ($a, $b) => $a->created_at <=> $b->created_at,
                fn ($a, $b) => $a->id <=> $b->id,
            ]);
            $points = $scores->map(fn ($s) => ['date' => $s->exam->exam_date->toDateString(), 'title' => $s->exam->title, 'exam_id' => $s->exam_id, 'score' => (float) $s->score, 'value' => (float) $s->score])->values();

            return ['key' => $key, 'name' => $group->first()->name, 'score_count' => $scores->count(), 'grade_count' => $group->where('mode', 'grade')->count(), 'unit' => '分', 'mean_score' => $scores->isEmpty() ? null : round($scores->avg('score'), 2), 'best_score' => $scores->isEmpty() ? null : (float) $scores->max('score'), 'points' => $points, 'grades' => $group->where('mode', 'grade')->groupBy('grade')->map(fn ($g, $label) => ['label' => (string) $label, 'count' => $g->count()])->values()];
        })->values()->all();
    }

    public function intentions(Collection $rows): array
    {
        return $rows->groupBy('school_key')->map(fn ($g, $key) => ['school' => $g->first()->school, 'key' => $key, 'students' => $g->pluck('student_id')->unique()->count(), 'first' => $g->where('level', 'first')->count(), 'backup' => $g->where('level', 'backup')->count()])->sortByDesc('students')->values()->all();
    }
}
