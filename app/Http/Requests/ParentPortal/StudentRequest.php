<?php

namespace App\Http\Requests\ParentPortal;

use App\Services\Shared\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'school'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => Text::clean($this->input($key))]);
            }
        }
        if ($this->exists('admitted_schools')) {
            $schools = $this->input('admitted_schools') ?? [];
            $this->merge(['admitted_schools' => is_array($schools)
                ? array_map(fn ($school) => is_string($school) ? Text::clean($school) : $school, $schools)
                : $schools]);
        }
    }

    public function messages(): array
    {
        return ['class_number.required' => '请填写班号', 'class_number.regex' => '班号只能填写阿拉伯数字（0–9）', 'class_number.integer' => '班号必须是 1–99 的整数', 'class_number.between' => '班号必须是 1–99 的整数',
            'admitted_school.prohibited' => '录取学校已支持多选，请刷新页面后重新保存。',
            'admitted_schools.*.in' => '请从列表中选择已被录取学校。',
            'admitted_schools.*.distinct' => '同一所学校不能重复选择。'];
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:50', 'gender' => ['required', Rule::in(['male', 'female', 'unspecified'])], 'school' => 'required|string|max:160',
            'admitted_school' => ['prohibited'],
            'admitted_schools' => ['sometimes', 'array', 'list', 'max:9'],
            'admitted_schools.*' => ['required', 'string', 'distinct:strict', Rule::in(Text::admittedSchools())],
            'class_number' => ['required', 'integer', 'between:1,99', 'regex:/\A[0-9]+\z/'], 'entry_year' => 'nullable|integer|min:1990|max:'.(now()->year + 1), 'entry_grade' => ['nullable', Rule::in(Text::grades())], 'current_grade' => ['required', Rule::in(Text::grades())], 'contact_phone' => 'required|regex:/^1[3-9][0-9]{9}$/'];
    }
}
