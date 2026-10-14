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
    }

    public function messages(): array
    {
        return ['class_number.required' => '请填写班号', 'class_number.regex' => '班号只能填写阿拉伯数字（0–9）', 'class_number.integer' => '班号必须是 1–99 的整数', 'class_number.between' => '班号必须是 1–99 的整数'];
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:50', 'gender' => ['required', Rule::in(['male', 'female', 'unspecified'])], 'school' => 'required|string|max:160', 'class_number' => ['required', 'integer', 'between:1,99', 'regex:/\A[0-9]+\z/'], 'entry_year' => 'nullable|integer|min:1990|max:'.(now()->year + 1), 'entry_grade' => ['nullable', Rule::in(Text::grades())], 'current_grade' => ['required', Rule::in(Text::grades())], 'contact_phone' => 'required|regex:/^1[3-9][0-9]{9}$/'];
    }
}
