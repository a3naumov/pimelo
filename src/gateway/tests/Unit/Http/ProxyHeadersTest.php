<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ProxyHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxyHeaders::class)]
final class ProxyHeadersTest extends TestCase
{
    // ========================================================================
    // Public redirect locations
    // ========================================================================

    #[DataProvider('locations')]
    public function testRewritesInternalRedirects(string $location, string $expected): void
    {
        self::assertSame($expected, new ProxyHeaders()->location($location, 'http://pim:8080', '/web/products/?x=1', 'https://example.test', '/pim'));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function locations(): iterable
    {
        yield ['http://pim:8080/web/products/1', 'https://example.test/pim/web/products/1'];

        yield ['/web/products/1', 'https://example.test/pim/web/products/1'];

        yield ['//pim:8080/web/products/1', 'https://example.test/pim/web/products/1'];

        yield ['../categories/', 'https://example.test/pim/web/categories/'];

        yield ['../../../', 'https://example.test/pim/'];

        yield ['?page=2', 'https://example.test/pim/web/products/?page=2'];

        yield ['https://external.test/redirect', 'https://external.test/redirect'];

        yield ['http://pim:8080.evil.test/path', 'http://pim:8080.evil.test/path'];
    }
}
