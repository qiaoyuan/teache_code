<?php

namespace App\Http\Requests\ParentPortal;

use App\Services\Shared\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('school'))) {
            $this->merge(['school' => Text::clean($this->input('school'))]);
        }
    }

    public function rules(): array
    {
        return ['school' => 'required|string|max:160', 'level' => ['required', Rule::in(['first', 'backup'])], 'note' => 'nullable|string|max:2000'];
    }
}
