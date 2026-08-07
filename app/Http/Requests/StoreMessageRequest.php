<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'uuid', 'exists:users,id'],
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body.max' => 'Message body cannot exceed 1000 characters.',
            'recipient_id.exists' => 'The specified recipient does not exist.',
        ];
    }
}
