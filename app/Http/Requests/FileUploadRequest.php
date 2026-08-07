<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SanitizationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates file uploads with security constraints:
 * - Allowed types: .md, .txt, .pdf, .png, .jpg, .jpeg, .gif
 * - Maximum file size: 10MB per file
 * - Maximum files per upload: 5
 */
class FileUploadRequest extends FormRequest
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
        $allowedExtensions = implode(',', SanitizationService::allowedFileExtensions());
        $maxSizeKb = SanitizationService::maxFileSizeKb();
        $maxFiles = SanitizationService::maxFilesPerUpload();

        return [
            'files' => ['required', 'array', "max:{$maxFiles}"],
            'files.*' => [
                'required',
                'file',
                "mimes:{$allowedExtensions}",
                "max:{$maxSizeKb}",
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $maxFiles = SanitizationService::maxFilesPerUpload();
        $maxSizeMb = SanitizationService::maxFileSizeKb() / 1024;
        $allowedExtensions = implode(', .', SanitizationService::allowedFileExtensions());

        return [
            'files.required' => 'At least one file is required.',
            'files.max' => "Maximum of {$maxFiles} files allowed per upload.",
            'files.*.mimes' => "Only .{$allowedExtensions} files are allowed.",
            'files.*.max' => "Each file must not exceed {$maxSizeMb}MB.",
            'files.*.file' => 'Each upload must be a valid file.',
        ];
    }
}
