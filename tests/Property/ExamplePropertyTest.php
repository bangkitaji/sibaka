<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Example property-based test using Eris.
 * Demonstrates the phpunit-quickcheck style generative testing integration.
 */
class ExamplePropertyTest extends TestCase
{
    use TestTrait;

    public function testStringConcatenationLength(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::string()
        )
            ->then(function (string $a, string $b) {
                $this->assertEquals(
                    strlen($a) + strlen($b),
                    strlen($a . $b),
                    'String concatenation length should be sum of individual lengths'
                );
            });
    }

    public function testIntegerAdditionIsCommutative(): void
    {
        $this->forAll(
            Generators::int(),
            Generators::int()
        )
            ->then(function (int $a, int $b) {
                $this->assertEquals(
                    $a + $b,
                    $b + $a,
                    'Integer addition should be commutative'
                );
            });
    }
}
