<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Adapter\Symfony\Identity;

use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidGenerator::class)]
#[UsesClass(Id::class)]
final class UuidGeneratorTest extends TestCase
{
    private IdGeneratorInterface $generator;

    protected function setUp(): void
    {
        $this->generator = new UuidGenerator();
    }

    // ========================================================================
    // Generation: returns a domain identity containing a UUID v7
    // ========================================================================

    public function testGenerateCreatesUuidV7Identity(): void
    {
        $id = $this->generator->generate();

        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $id->toString(),
        );
        self::assertTrue($id->equals(Id::fromString($id->toString())));
    }

    // ========================================================================
    // Uniqueness: consecutive calls return different identity values
    // ========================================================================

    public function testGenerateCreatesDifferentValues(): void
    {
        $first = $this->generator->generate();
        $second = $this->generator->generate();

        self::assertNotSame($first->toString(), $second->toString());
        self::assertFalse($first->equals($second));
    }
}
