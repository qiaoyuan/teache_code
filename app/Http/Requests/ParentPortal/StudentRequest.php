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

    public function rules(): array
    {
        return ['name' => 'required|string|max:50', 'gender' => ['required', Rule::in(['male', 'female', 'unspecified'])], 'school' => 'required|string|max:160', 'entry_year' => 'required|integer|min:1990|max:'.(now()->year + 1), 'entry_grade' => ['required', Rule::in(Text::grades())], 'current_grade' => ['required', Rule::in(Text::grades())], 'contact_phone' => 'required|regex:/^1[3-9][0-9]{9}$/'];
    }
}
