<?php

namespace App\Http\Requests\Admin;

use App\Services\Shared\Text;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['password' => [...Text::passwordRules(), 'confirmed']];
    }
}
