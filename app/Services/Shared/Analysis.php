<?php

namespace App\Services\Shared;

use Illuminate\Support\Collection;

class Analysis
{
    public function subjects(Collection $rows): array
    {
        return $rows->groupBy('subject_key')->map(function ($group, $key) {
            $scores = $group->where('mode', 'score')->sortBy(fn ($s) => $s->exam->exam_date->toDateString().' '.$s->created_at);
            $normalized = $scores->pluck('max_score')->unique()->count() > 1;
            $points = $scores->map(fn ($s) => ['date' => $s->exam->exam_date->toDateString(), 'title' => $s->exam->title, 'exam_id' => $s->exam_id, 'score' => (float) $s->score, 'max_score' => (float) $s->max_score, 'rate' => round((float) $s->score / (float) $s->max_score * 100, 2), 'value' => $normalized ? round((float) $s->score / (float) $s->max_score * 100, 2) : (float) $s->score])->values();
            $rates = $scores->map(fn ($s) => (float) $s->score / (float) $s->max_score * 100);

            return ['key' => $key, 'name' => $group->first()->name, 'score_count' => $scores->count(), 'grade_count' => $group->where('mode', 'grade')->count(), 'normalized' => $normalized, 'unit' => $normalized ? '得分率 %' : '分', 'max_score' => $scores->first()?->max_score, 'mean_rate' => $rates->isEmpty() ? null : round($rates->avg(), 2), 'best_rate' => $rates->isEmpty() ? null : round($rates->max(), 2), 'best_score' => $scores->isEmpty() ? null : (float) $scores->max('score'), 'points' => $points, 'grades' => $group->where('mode', 'grade')->groupBy('grade')->map(fn ($g, $label) => ['label' => (string) $label, 'count' => $g->count()])->values()];
        })->values()->all();
    }

    public function intentions(Collection $rows): array
    {
        return $rows->groupBy('school_key')->map(fn ($g, $key) => ['school' => $g->first()->school, 'key' => $key, 'students' => $g->pluck('student_id')->unique()->count(), 'first' => $g->where('level', 'first')->count(), 'backup' => $g->where('level', 'backup')->count()])->sortByDesc('students')->values()->all();
    }
}
