<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SanitizationService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Sanitizes text fields to prevent XSS attacks.
     */
    protected function prepareForValidation(): void
    {
        $sanitizer = app(SanitizationService::class);
        $fieldsToSanitize = ['job_title', 'company', 'primary_tech_stack', 'secondary_tech_stack'];

        $sanitized = [];
        foreach ($fieldsToSanitize as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = $sanitizer->sanitize($this->input($field));
            }
        }

        if (!empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_title' => ['required', 'string', 'min:1', 'max:100'],
            'company' => ['required', 'string', 'min:1', 'max:100'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:50'],
            'primary_tech_stack' => ['required', 'string', 'min:1', 'max:200'],
            'secondary_tech_stack' => ['nullable', 'string', 'max:200'],
            'mentorship_status' => ['nullable', 'in:willing,not_willing'],
            'hiring_status' => ['nullable', 'in:open_to_hiring,seeking_job,internship,none'],
            'availability' => ['nullable', 'in:immediate,1_month,2_months,3_months_plus'],
        ];
    }
}
