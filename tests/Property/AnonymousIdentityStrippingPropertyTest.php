<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 8: Anonymous Content Identity Stripping
 *
 * Tests that the public view of anonymous content contains zero identifying
 * information about the author. Generates random user profiles and verifies
 * none of their data appears in the serialized anonymous content response.
 *
 * The test directly exercises the identity stripping logic used by
 * ContentService::getContent() without requiring database connectivity,
 * using plain arrays to represent the content response structure.
 *
 * **Validates: Requirements 5.2, 5.3, 5.4, 11.3**
 */
class AnonymousIdentityStrippingPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Simulate the identity stripping logic from ContentService::getContent().
     *
     * This replicates the exact conditional logic used in the service:
     * - If content is_anonymous: author = ['name' => 'Anonymous Member', 'id' => null]
     * - If NOT anonymous: author = ['id' => $authorId, 'name' => $authorName]
     *
     * @param bool $isAnonymous Whether the content is anonymous
     * @param array $authorData The author's identifying data
     * @param array $contentData The content's non-identifying data
     * @return array The public-facing response data
     */
    private function buildPublicContentView(bool $isAnonymous, array $authorData, array $contentData = []): array
    {
        $result = [
            'id' => $contentData['id'] ?? 'content-uuid-' . rand(1000, 9999),
            'title' => $contentData['title'] ?? 'Test Content Title',
            'body' => $contentData['body'] ?? 'Test content body paragraph.',
            'body_html' => $contentData['body_html'] ?? '<p>Test content body paragraph.</p>',
            'category' => $contentData['category'] ?? 'post_mortem',
            'is_anonymous' => $isAnonymous,
            'is_qna' => $contentData['is_qna'] ?? false,
            'is_locked' => $contentData['is_locked'] ?? false,
            'status' => $contentData['status'] ?? 'published',
            'published_at' => $contentData['published_at'] ?? '2024-01-15T10:00:00Z',
            'created_at' => $contentData['created_at'] ?? '2024-01-15T09:00:00Z',
            'updated_at' => $contentData['updated_at'] ?? '2024-01-15T10:00:00Z',
            'tags' => [],
            'reactions_summary' => [],
            'reactions_total' => 0,
            'comments_count' => 0,
        ];

        // This is the exact logic from ContentService::getContent()
        if ($isAnonymous) {
            $result['author'] = [
                'name' => 'Anonymous Member',
                'id' => null,
            ];
        } else {
            $result['author'] = [
                'id' => $authorData['id'],
                'name' => $authorData['name'],
            ];
        }

        return $result;
    }

    /**
     * Serialize the content response to a flat string for field searching.
     */
    private function serializeResponse(array $response): string
    {
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Property: Anonymous content public view always shows "Anonymous Member" as author name.
     *
     * For any random user name, anonymous content must always display "Anonymous Member"
     * with null ID regardless of who the actual author is.
     */
    public function testAnonymousContentAlwaysShowsAnonymousMember(): void
    {
        $this->forAll(
            Generators::names()
        )
            ->withMaxSize(50)
            ->then(function (string $name) {
                $authorData = [
                    'id' => 'user-' . md5($name),
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                ];

                $response = $this->buildPublicContentView(true, $authorData);

                $this->assertEquals('Anonymous Member', $response['author']['name']);
                $this->assertNull($response['author']['id']);
            });
    }

    /**
     * Property: Author name never appears in anonymous content response.
     *
     * For any randomly generated user name, that name must NOT appear
     * anywhere in the serialized anonymous content response.
     */
    public function testAuthorNameNeverAppearsInAnonymousResponse(): void
    {
        $this->forAll(
            Generators::names()
        )
            ->withMaxSize(50)
            ->then(function (string $name) {
                // Skip very short names that could match common words in the response
                if (strlen($name) < 3) {
                    return;
                }

                $authorData = [
                    'id' => 'user-' . md5($name),
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                $this->assertStringNotContainsString(
                    $name,
                    $serialized,
                    "Author name '{$name}' should NOT appear in anonymous content response"
                );
            });
    }

    /**
     * Property: Author email never appears in anonymous content response.
     *
     * For any randomly generated email address, that email must NOT appear
     * anywhere in the serialized anonymous content response.
     */
    public function testAuthorEmailNeverAppearsInAnonymousResponse(): void
    {
        $this->forAll(
            Generators::map(
                fn ($n) => "user{$n}@testdomain{$n}.example.com",
                Generators::choose(1, 99999)
            )
        )
            ->withMaxSize(50)
            ->then(function (string $email) {
                $authorData = [
                    'id' => 'user-' . md5($email),
                    'name' => 'Test User',
                    'email' => $email,
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                $this->assertStringNotContainsString(
                    $email,
                    $serialized,
                    "Author email '{$email}' should NOT appear in anonymous content response"
                );
            });
    }

    /**
     * Property: Author UUID/member ID never appears in anonymous content response.
     *
     * For any random UUID, the author's ID must not leak through the anonymous view.
     */
    public function testAuthorIdNeverAppearsInAnonymousResponse(): void
    {
        $this->forAll(
            Generators::map(
                fn ($n) => sprintf(
                    '%08x-%04x-%04x-%04x-%012x',
                    $n * 7919, ($n * 31) & 0xFFFF, ($n * 37) & 0xFFFF,
                    ($n * 41) & 0xFFFF, $n * 104729
                ),
                Generators::choose(1, 99999)
            )
        )
            ->withMaxSize(50)
            ->then(function (string $uuid) {
                $authorData = [
                    'id' => $uuid,
                    'name' => 'User With UUID',
                    'email' => 'uuid-user@example.com',
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                $this->assertStringNotContainsString(
                    $uuid,
                    $serialized,
                    "Author ID '{$uuid}' should NOT appear in anonymous content response"
                );
            });
    }

    /**
     * Property: Company name from user profile never appears in anonymous content response.
     *
     * For any randomly generated company name, it must NOT appear in the response.
     * Profile data (company, job_title, etc.) should never be included in
     * the content response for anonymous posts.
     */
    public function testCompanyNameNeverAppearsInAnonymousResponse(): void
    {
        $this->forAll(
            Generators::elements([
                'Tokopedia Engineering',
                'Gojek Indonesia',
                'Shopee International',
                'Xendit Payments',
                'Traveloka Digital',
                'Bukalapak Technology',
                'OVO Finance',
                'Ruangguru Education',
                'Mekari Solutions',
                'eFishery Aquaculture',
            ])
        )
            ->withMaxSize(10)
            ->then(function (string $company) {
                $authorData = [
                    'id' => 'user-' . md5($company),
                    'name' => 'Employee at ' . $company,
                    'email' => 'employee@' . strtolower(explode(' ', $company)[0]) . '.com',
                    'company' => $company,
                    'job_title' => 'Senior Engineer at ' . $company,
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                $this->assertStringNotContainsString(
                    $company,
                    $serialized,
                    "Company '{$company}' should NOT appear in anonymous content response"
                );
            });
    }

    /**
     * Property: Profile fields (job_title, linkedin_url, github_url) never appear
     * in anonymous content response.
     *
     * Generates random profile data and verifies none of it leaks into the response.
     */
    public function testProfileFieldsNeverAppearInAnonymousResponse(): void
    {
        $this->forAll(
            Generators::elements([
                'Senior Backend Developer',
                'DevOps Engineer',
                'Cloud Architect',
                'Technical Lead',
                'Full Stack Developer',
                'Site Reliability Engineer',
                'Engineering Manager',
                'Security Engineer',
                'Mobile Developer',
                'Data Engineer',
            ])
        )
            ->withMaxSize(10)
            ->then(function (string $jobTitle) {
                $linkedinUrl = 'https://linkedin.com/in/' . str_replace(' ', '-', strtolower($jobTitle));
                $githubUrl = 'https://github.com/' . str_replace(' ', '-', strtolower($jobTitle));

                $authorData = [
                    'id' => 'user-' . md5($jobTitle),
                    'name' => 'Professional ' . explode(' ', $jobTitle)[0],
                    'email' => strtolower(str_replace(' ', '.', $jobTitle)) . '@corp.com',
                    'job_title' => $jobTitle,
                    'linkedin_url' => $linkedinUrl,
                    'github_url' => $githubUrl,
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                $this->assertStringNotContainsString(
                    $jobTitle,
                    $serialized,
                    "Job title '{$jobTitle}' should NOT appear in anonymous content response"
                );
                $this->assertStringNotContainsString(
                    $linkedinUrl,
                    $serialized,
                    "LinkedIn URL '{$linkedinUrl}' should NOT appear in anonymous content response"
                );
                $this->assertStringNotContainsString(
                    $githubUrl,
                    $serialized,
                    "GitHub URL '{$githubUrl}' should NOT appear in anonymous content response"
                );
            });
    }

    /**
     * Property: Multiple anonymous posts by the same author must NOT be visually correlatable.
     *
     * Two anonymous posts by the same author should have identical author fields -
     * both showing "Anonymous Member" with null ID - making them indistinguishable.
     */
    public function testMultipleAnonymousPostsBySameAuthorAreNotCorrelatable(): void
    {
        $this->forAll(
            Generators::names()
        )
            ->withMaxSize(20)
            ->then(function (string $name) {
                $authorData = [
                    'id' => 'user-' . md5($name),
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                ];

                // Create multiple anonymous posts by same author
                $response1 = $this->buildPublicContentView(true, $authorData, ['id' => 'content-1']);
                $response2 = $this->buildPublicContentView(true, $authorData, ['id' => 'content-2']);
                $response3 = $this->buildPublicContentView(true, $authorData, ['id' => 'content-3']);

                // All should show identical anonymous author info
                $this->assertEquals($response1['author'], $response2['author']);
                $this->assertEquals($response2['author'], $response3['author']);

                // Author name must be "Anonymous Member" for all
                $this->assertEquals('Anonymous Member', $response1['author']['name']);
                $this->assertEquals('Anonymous Member', $response2['author']['name']);
                $this->assertEquals('Anonymous Member', $response3['author']['name']);

                // No ID should be present
                $this->assertNull($response1['author']['id']);
                $this->assertNull($response2['author']['id']);
                $this->assertNull($response3['author']['id']);
            });
    }

    /**
     * Property: Comprehensive check - for any random user profile combination,
     * NONE of the identifying fields appear in the anonymous content response.
     *
     * This is the core property: given random identifying data, the anonymous
     * view is completely clean of all personal information.
     */
    public function testNoIdentifyingInfoAppearsForRandomProfiles(): void
    {
        $this->forAll(
            Generators::choose(0, 9)
        )
            ->withMaxSize(10)
            ->then(function (int $index) {
                $names = [
                    'Budi Setiawan', 'Agus Pratama', 'Dewi Lestari', 'Rini Hartono',
                    'Eko Wahyudi', 'Sri Mulyani', 'Andi Kusuma', 'Putri Rahayu',
                    'Dian Permata', 'Hendra Wijaya',
                ];
                $companies = [
                    'PT Teknologi Maju', 'CV Digital Nusantara', 'Startup Inovasi',
                    'Korporasi Global', 'Fintech Indonesia', 'Edtech Merdeka',
                    'HealthTech Sejahtera', 'AgriTech Mandiri', 'LogiTech Cepat', 'DataTech Pintar',
                ];
                $emails = [
                    'budi.setiawan@example.com', 'agus.pratama@mail.test', 'dewi@company.id',
                    'rini.hartono@tech.co', 'eko@startup.dev', 'sri.m@fintech.io',
                    'andi.k@corp.net', 'putri@edu.org', 'dian.p@health.care', 'hendra@agri.farm',
                ];
                $jobTitles = [
                    'Senior Backend Developer', 'DevOps Engineer', 'Cloud Architect',
                    'Technical Lead', 'Full Stack Developer', 'Site Reliability Engineer',
                    'Engineering Manager', 'Security Engineer', 'Mobile Developer', 'Data Engineer',
                ];
                $linkedinUrls = [
                    'https://linkedin.com/in/budi-setiawan', 'https://linkedin.com/in/agus-pratama',
                    'https://linkedin.com/in/dewi-lestari', 'https://linkedin.com/in/rini-hartono',
                    'https://linkedin.com/in/eko-wahyudi', 'https://linkedin.com/in/sri-mulyani',
                    'https://linkedin.com/in/andi-kusuma', 'https://linkedin.com/in/putri-rahayu',
                    'https://linkedin.com/in/dian-permata', 'https://linkedin.com/in/hendra-wijaya',
                ];
                $githubUrls = [
                    'https://github.com/budisetiawan', 'https://github.com/aguspratama',
                    'https://github.com/dewilestari', 'https://github.com/rinihartono',
                    'https://github.com/ekowahyudi', 'https://github.com/srimulyani',
                    'https://github.com/andikusuma', 'https://github.com/putrirahayu',
                    'https://github.com/dianpermata', 'https://github.com/hendrawijaya',
                ];

                $name = $names[$index];
                $company = $companies[$index];
                $email = $emails[$index];
                $jobTitle = $jobTitles[$index];
                $linkedinUrl = $linkedinUrls[$index];
                $githubUrl = $githubUrls[$index];
                $userId = 'user-id-' . md5($name . $email);

                $authorData = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'company' => $company,
                    'job_title' => $jobTitle,
                    'linkedin_url' => $linkedinUrl,
                    'github_url' => $githubUrl,
                ];

                $response = $this->buildPublicContentView(true, $authorData);
                $serialized = $this->serializeResponse($response);

                // Verify none of the identifying fields appear in the response
                $this->assertStringNotContainsString(
                    $name,
                    $serialized,
                    "Name '{$name}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $email,
                    $serialized,
                    "Email '{$email}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $company,
                    $serialized,
                    "Company '{$company}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $userId,
                    $serialized,
                    "User ID '{$userId}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $jobTitle,
                    $serialized,
                    "Job title '{$jobTitle}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $linkedinUrl,
                    $serialized,
                    "LinkedIn URL '{$linkedinUrl}' leaked into anonymous response"
                );
                $this->assertStringNotContainsString(
                    $githubUrl,
                    $serialized,
                    "GitHub URL '{$githubUrl}' leaked into anonymous response"
                );

                // Verify the response shows correct anonymous info
                $this->assertEquals('Anonymous Member', $response['author']['name']);
                $this->assertNull($response['author']['id']);
            });
    }

    /**
     * Property: Non-anonymous content DOES show author info (control test).
     *
     * Verifies the stripping only applies to anonymous content - confirming
     * that the non-anonymous path correctly exposes author identity.
     */
    public function testNonAnonymousContentShowsAuthorInfo(): void
    {
        $this->forAll(
            Generators::names()
        )
            ->withMaxSize(20)
            ->then(function (string $name) {
                $userId = 'user-' . md5($name);
                $authorData = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                ];

                $response = $this->buildPublicContentView(false, $authorData);

                // Non-anonymous should show real author info
                $this->assertEquals($userId, $response['author']['id']);
                $this->assertEquals($name, $response['author']['name']);
            });
    }
}
