<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'graduation_year' => ['required', 'integer', 'min:1979', 'max:' . date('Y')],
            'department' => ['required', 'string', 'min:1', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:200'],
            'github_url' => ['nullable', 'url', 'max:200'],
            'invite_code' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'The email address is already registered.',
            'graduation_year.min' => 'The graduation year must be 1979 or later.',
            'graduation_year.max' => 'The graduation year cannot be in the future.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }
}
