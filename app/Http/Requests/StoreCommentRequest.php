<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SanitizationService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
     * Trim whitespace and sanitize the text field before validation.
     */
    protected function prepareForValidation(): void
    {
        $sanitizer = app(SanitizationService::class);
        $text = trim($this->text ?? '');
        $text = $sanitizer->sanitize($text);

        $this->merge([
            'text' => $text,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['nullable', 'uuid', 'exists:comments,id'],
            'is_anonymous' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Comment text is required.',
            'text.min' => 'Comment must be at least 1 character after trimming whitespace.',
            'text.max' => 'Comment must not exceed 5000 characters.',
            'parent_id.uuid' => 'Invalid parent comment reference.',
            'parent_id.exists' => 'The parent comment does not exist.',
        ];
    }
}
