<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Property 1: Registration and Profile Validation Schema Correctness
 *
 * Tests that registration validator accepts valid inputs and rejects invalid
 * inputs across randomized data.
 *
 * **Validates: Requirements 1.2, 2.3, 2.4**
 */
class RegistrationValidationTest extends TestCase
{
    use TestTrait;

    /**
     * Get the registration validation rules (mirrors RegisterRequest).
     */
    private function registrationRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'graduation_year' => ['required', 'integer', 'min:1979', 'max:' . date('Y')],
            'department' => ['required', 'string', 'min:1', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:200'],
            'github_url' => ['nullable', 'url', 'max:200'],
        ];
    }

    /**
     * Property: Any data matching all registration constraints passes validation.
     *
     * For any randomly generated valid inputs (name 1-100 chars, valid email,
     * password 8+ chars, graduation_year 1979-current, department 1-100 chars,
     * valid URLs or null), the validator must pass.
     */
    public function testValidRegistrationDataPassesValidation(): void
    {
        $currentYear = (int) date('Y');

        $this->forAll(
            Generators::suchThat(
                fn ($name) => strlen($name) >= 1 && strlen($name) <= 100 && is_string($name),
                Generators::string()
            ),
            Generators::choose(8, 64),        // password length
            Generators::choose(1979, $currentYear), // graduation year
            Generators::suchThat(
                fn ($dept) => strlen($dept) >= 1 && strlen($dept) <= 100 && is_string($dept),
                Generators::string()
            )
        )
            ->withMaxSize(50)
            ->then(function (string $name, int $passwordLength, int $graduationYear, string $department) {
                $password = str_repeat('a', $passwordLength);
                $email = 'user' . mt_rand(1, 999999) . '@example.com';

                $data = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'graduation_year' => $graduationYear,
                    'department' => $department,
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid registration data should pass validation. Errors: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property: Name > 100 chars always fails validation.
     */
    public function testNameExceeding100CharsFailsValidation(): void
    {
        $currentYear = (int) date('Y');

        $this->forAll(
            Generators::choose(101, 300) // name length
        )
            ->then(function (int $nameLength) use ($currentYear) {
                $name = str_repeat('A', $nameLength);
                $data = [
                    'name' => $name,
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'graduation_year' => mt_rand(1979, $currentYear),
                    'department' => 'IT',
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Name with {$nameLength} chars should fail validation"
                );
                $this->assertArrayHasKey('name', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Password < 8 chars always fails validation.
     */
    public function testPasswordLessThan8CharsFailsValidation(): void
    {
        $currentYear = (int) date('Y');

        $this->forAll(
            Generators::choose(1, 7) // password length (1-7 = too short)
        )
            ->then(function (int $passwordLength) use ($currentYear) {
                $password = str_repeat('x', $passwordLength);
                $data = [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => $password,
                    'graduation_year' => mt_rand(1979, $currentYear),
                    'department' => 'IT',
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Password with {$passwordLength} chars should fail validation"
                );
                $this->assertArrayHasKey('password', $validator->errors()->toArray());
            });
    }

    /**
     * Property: graduation_year outside 1979-current always fails validation.
     */
    public function testGraduationYearOutOfRangeFailsValidation(): void
    {
        $currentYear = (int) date('Y');

        // Test years below 1979
        $this->forAll(
            Generators::choose(1900, 1978) // too early
        )
            ->then(function (int $year) {
                $data = [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'graduation_year' => $year,
                    'department' => 'IT',
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Graduation year {$year} (before 1979) should fail validation"
                );
                $this->assertArrayHasKey('graduation_year', $validator->errors()->toArray());
            });

        // Test years above current year
        $this->forAll(
            Generators::choose($currentYear + 1, $currentYear + 100) // future years
        )
            ->then(function (int $year) {
                $data = [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'graduation_year' => $year,
                    'department' => 'IT',
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Graduation year {$year} (after current year) should fail validation"
                );
                $this->assertArrayHasKey('graduation_year', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Invalid email format always fails validation.
     */
    public function testInvalidEmailFormatFailsValidation(): void
    {
        $currentYear = (int) date('Y');

        // Generate strings that are definitely not valid emails
        // Note: Laravel's 'email' rule uses filter_var(FILTER_VALIDATE_EMAIL) by default,
        // which accepts emails like 'user@domain' (without TLD). We test formats that
        // always fail the validator.
        $invalidEmails = [
            'notanemail',
            '@nodomain.com',
            'spaces in@email.com',
            'no-at-sign.com',
            'double@@at.com',
            '',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $data = [
                'name' => 'Test User',
                'email' => $invalidEmail,
                'password' => 'password123',
                'graduation_year' => mt_rand(1979, $currentYear),
                'department' => 'IT',
                'linkedin_url' => null,
                'github_url' => null,
            ];

            $validator = Validator::make($data, $this->registrationRules());

            $this->assertTrue(
                $validator->fails(),
                "Invalid email '{$invalidEmail}' should fail validation"
            );
            $this->assertArrayHasKey('email', $validator->errors()->toArray());
        }

        // Property-based: strings without @ always fail email validation
        $this->forAll(
            Generators::suchThat(
                fn ($s) => !str_contains($s, '@') && strlen($s) > 0,
                Generators::string()
            )
        )
            ->withMaxSize(50)
            ->then(function (string $invalidEmail) use ($currentYear) {
                $data = [
                    'name' => 'Test User',
                    'email' => $invalidEmail,
                    'password' => 'password123',
                    'graduation_year' => mt_rand(1979, $currentYear),
                    'department' => 'IT',
                    'linkedin_url' => null,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Email without @ sign should fail: '{$invalidEmail}'"
                );
                $this->assertArrayHasKey('email', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Valid registration data with valid optional URLs passes validation.
     */
    public function testValidDataWithOptionalUrlsPassesValidation(): void
    {
        $currentYear = (int) date('Y');

        $this->forAll(
            Generators::choose(1979, $currentYear), // graduation year
            Generators::choose(8, 50)               // password length
        )
            ->then(function (int $graduationYear, int $passwordLength) {
                $data = [
                    'name' => 'Valid Name',
                    'email' => 'user' . mt_rand(1, 999999) . '@example.com',
                    'password' => str_repeat('p', $passwordLength),
                    'graduation_year' => $graduationYear,
                    'department' => 'Computer Science',
                    'linkedin_url' => 'https://linkedin.com/in/testuser',
                    'github_url' => 'https://github.com/testuser',
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid data with URLs should pass. Errors: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property: Empty name always fails validation.
     */
    public function testEmptyNameFailsValidation(): void
    {
        $currentYear = (int) date('Y');

        $emptyValues = ['', null];

        foreach ($emptyValues as $emptyName) {
            $data = [
                'name' => $emptyName,
                'email' => 'test@example.com',
                'password' => 'password123',
                'graduation_year' => mt_rand(1979, $currentYear),
                'department' => 'IT',
                'linkedin_url' => null,
                'github_url' => null,
            ];

            $validator = Validator::make($data, $this->registrationRules());

            $this->assertTrue(
                $validator->fails(),
                "Empty name should fail validation"
            );
            $this->assertArrayHasKey('name', $validator->errors()->toArray());
        }
    }

    /**
     * Property: URLs exceeding 200 chars always fail validation.
     */
    public function testUrlsExceeding200CharsFailValidation(): void
    {
        $currentYear = (int) date('Y');

        $this->forAll(
            Generators::choose(201, 400) // URL length
        )
            ->then(function (int $urlLength) use ($currentYear) {
                $longUrl = 'https://linkedin.com/in/' . str_repeat('a', $urlLength - 24);

                $data = [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'graduation_year' => mt_rand(1979, $currentYear),
                    'department' => 'IT',
                    'linkedin_url' => $longUrl,
                    'github_url' => null,
                ];

                $validator = Validator::make($data, $this->registrationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "LinkedIn URL with {$urlLength} chars should fail validation"
                );
                $this->assertArrayHasKey('linkedin_url', $validator->errors()->toArray());
            });
    }
}
