<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Any authenticated member can report content.
     */
    public function authorize(): bool
    {
        return $this->user()?->isMember() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content_id' => ['required', 'uuid', 'exists:contents,id'],
            'reason' => ['required', 'in:spam,harassment,misinformation,off_topic,other'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A report reason is required.',
            'reason.in' => 'The report reason must be one of: spam, harassment, misinformation, off_topic, other.',
            'description.max' => 'The description must not exceed 500 characters.',
        ];
    }
}
