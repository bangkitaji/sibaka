<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContentCategory;
use App\Enums\TagCategory;
use App\Models\Content;
use App\Models\Tag;
use App\Services\SanitizationService;
use App\Services\TagService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $content = Content::findOrFail($this->route('content'));

        return $this->user()->can('update', $content);
    }

    /**
     * Prepare the data for validation.
     * Sanitizes user-generated content fields to prevent XSS attacks.
     */
    protected function prepareForValidation(): void
    {
        $sanitizer = app(SanitizationService::class);

        $sanitized = [];

        if ($this->has('title')) {
            $sanitized['title'] = $sanitizer->sanitize($this->input('title') ?? '');
        }

        if ($this->has('body')) {
            $sanitized['body'] = $sanitizer->sanitize($this->input('body') ?? '');
        }

        if (!empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:200'],
            'body' => ['nullable', 'string', 'min:1', 'max:50000'],
            'category' => ['sometimes', 'in:post_mortem,tech_stack,career_interview,showcase'],
            'is_anonymous' => ['boolean'],
            'is_qna' => ['boolean'],
            'tags.tech_stack' => ['sometimes', 'array', 'min:1', 'max:3'],
            'tags.tech_stack.*' => ['required', 'string'],
            'tags.experience_level' => ['sometimes', 'string'],
            'tags.category' => ['sometimes', 'string'],
            'embeds' => ['nullable', 'array', 'max:10'],
            'embeds.*' => ['url'],
        ];
    }

    /**
     * Configure the validator instance.
     * Enforces tag existence in predefined list, category tag mapping,
     * and anonymous content irreversibility.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateAnonymousIrreversibility($validator);
            $this->validateTechStackTags($validator);
            $this->validateExperienceLevelTag($validator);
            $this->validateCategoryTag($validator);
            $this->validateCategoryTagMapping($validator);
        });
    }

    /**
     * Validate that anonymous content cannot be converted to non-anonymous.
     * Once content is published as anonymous, is_anonymous cannot be set to false.
     */
    private function validateAnonymousIrreversibility(Validator $validator): void
    {
        // Only check if is_anonymous is being explicitly set to false
        if ($this->has('is_anonymous') && $this->input('is_anonymous') === false) {
            $content = Content::find($this->route('content'));

            if ($content && $content->is_anonymous) {
                $validator->errors()->add(
                    'is_anonymous',
                    'Anonymous content cannot be converted to non-anonymous.'
                );
            }
        }
    }

    /**
     * Validate that all tech_stack tags exist in the predefined list with tech_stack category.
     */
    private function validateTechStackTags(Validator $validator): void
    {
        $techStackTags = $this->input('tags.tech_stack');

        if (!is_array($techStackTags) || empty($techStackTags)) {
            return;
        }

        $existingCount = Tag::byCategory(TagCategory::TechStack)
            ->whereIn('name', $techStackTags)
            ->count();

        if ($existingCount !== count($techStackTags)) {
            $existingNames = Tag::byCategory(TagCategory::TechStack)
                ->whereIn('name', $techStackTags)
                ->pluck('name')
                ->toArray();

            $invalidTags = array_diff($techStackTags, $existingNames);

            foreach ($invalidTags as $invalidTag) {
                $validator->errors()->add(
                    'tags.tech_stack',
                    "The tag '{$invalidTag}' is not a valid predefined Tech Stack tag."
                );
            }
        }
    }

    /**
     * Validate that the experience_level tag exists in the predefined list.
     */
    private function validateExperienceLevelTag(Validator $validator): void
    {
        $experienceLevel = $this->input('tags.experience_level');

        if (!$experienceLevel || !is_string($experienceLevel)) {
            return;
        }

        $exists = Tag::byCategory(TagCategory::ExperienceLevel)
            ->where('name', $experienceLevel)
            ->exists();

        if (!$exists) {
            $validator->errors()->add(
                'tags.experience_level',
                "The tag '{$experienceLevel}' is not a valid predefined Experience Level tag."
            );
        }
    }

    /**
     * Validate that the category tag exists in the predefined list.
     */
    private function validateCategoryTag(Validator $validator): void
    {
        $categoryTag = $this->input('tags.category');

        if (!$categoryTag || !is_string($categoryTag)) {
            return;
        }

        $exists = Tag::byCategory(TagCategory::Category)
            ->where('name', $categoryTag)
            ->exists();

        if (!$exists) {
            $validator->errors()->add(
                'tags.category',
                "The tag '{$categoryTag}' is not a valid predefined Category tag."
            );
        }
    }

    /**
     * Validate that the category tag maps to the content's IT_Experience_Category.
     */
    private function validateCategoryTagMapping(Validator $validator): void
    {
        $category = $this->input('category');
        $categoryTag = $this->input('tags.category');

        if (!$category || !$categoryTag) {
            return;
        }

        $contentCategory = ContentCategory::tryFrom($category);

        if (!$contentCategory) {
            return;
        }

        $expectedTag = TagService::categoryTagForContentCategory($contentCategory);

        if ($categoryTag !== $expectedTag) {
            $validator->errors()->add(
                'tags.category',
                "The category tag must be '{$expectedTag}' for {$contentCategory->label()} content."
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body.max' => 'Content exceeds maximum 50,000 characters.',
            'embeds.max' => 'Maximum embed limit of 10 reached.',
            'tags.tech_stack.min' => 'At least 1 Tech Stack tag is required.',
            'tags.tech_stack.max' => 'Maximum of 3 Tech Stack tags allowed.',
        ];
    }
}
