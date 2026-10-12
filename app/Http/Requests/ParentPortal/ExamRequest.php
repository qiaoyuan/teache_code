<?php

namespace App\Http\Requests\ParentPortal;

use App\Services\Shared\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'school_at_exam'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => Text::clean($this->input($key))]);
            }
        }
        if (is_array($this->input('subjects'))) {
            $this->merge(['subjects' => array_map(function ($s) {
                if (is_array($s) && is_string($s['name'] ?? null)) {
                    $s['name'] = Text::clean($s['name']);
                } if (is_array($s) && is_string($s['grade'] ?? null)) {
                    $s['grade'] = Text::key($s['grade']);
                }

                return $s;
            }, $this->input('subjects'))]);
        }
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:100', 'exam_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Shanghai')->toDateString(), 'grade_at_exam' => ['required', Rule::in(Text::grades())], 'school_at_exam' => 'required|string|max:160', 'note' => 'nullable|string|max:2000', 'version' => [$this->isMethod('PUT') ? 'required' : 'sometimes', 'integer', 'min:1'], 'subjects' => 'required|array|min:1|max:50', 'subjects.*.name' => 'required|string|max:80', 'subjects.*.mode' => ['required', Rule::in(['score', 'grade'])], 'subjects.*.score' => 'nullable|numeric|decimal:0,2|min:0|max:10000', 'subjects.*.max_score' => 'nullable|numeric|decimal:0,2|gt:0|max:10000', 'subjects.*.grade' => 'nullable|string|max:30'];
    }

    public function after(): array
    {
        return [function ($v) {
            $seen = [];
            foreach ((array) $this->input('subjects', []) as $i => $s) {
                if (! is_array($s)) {
                    continue;
                }
                $key = Text::key((string) ($s['name'] ?? ''));
                if (isset($seen[$key])) {
                    $v->errors()->add("subjects.$i.name", '同一次考试科目不能重复');
                } $seen[$key] = true;
                if (($s['mode'] ?? '') === 'score') {
                    if (! isset($s['score'],$s['max_score'])) {
                        $v->errors()->add("subjects.$i.score", '请同时填写分数和满分');
                    } if (isset($s['grade']) && $s['grade'] !== '') {
                        $v->errors()->add("subjects.$i.grade", '分数和等级只能选择一种');
                    } if (is_numeric($s['score'] ?? null) && is_numeric($s['max_score'] ?? null) && (float) $s['score'] > (float) $s['max_score']) {
                        $v->errors()->add("subjects.$i.score", '分数不能超过满分');
                    }
                }
                if (($s['mode'] ?? '') === 'grade') {
                    if (trim((string) ($s['grade'] ?? '')) === '') {
                        $v->errors()->add("subjects.$i.grade", '请填写等级');
                    } if (isset($s['score']) || isset($s['max_score'])) {
                        $v->errors()->add("subjects.$i.score", '等级模式不能填写分数');
                    }
                }
            }
        }];
    }
}
