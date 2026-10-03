<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ProxyRequestBody;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ProxyRequestBody::class)]
final class ProxyRequestBodyTest extends TestCase
{
    // ========================================================================
    // Raw and parsed request bodies
    // ========================================================================

    public function testPreservesRawFormEncoding(): void
    {
        $headers = [];
        $body = 'tag=a&tag=b&name=hello%20world';
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: $body);

        self::assertSame($body, new ProxyRequestBody()->prepare($request, $headers));
    }

    public function testRebuildsRoadRunnerParsedUrlencodedFields(): void
    {
        $headers = [];
        $request = Request::create('/', 'POST', ['name' => 'hello world', 'tags' => ['a', 'b']], server: [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], content: '{"name":"hello world","tags":["a","b"]}');

        self::assertSame('name=hello+world&tags%5B0%5D=a&tags%5B1%5D=b', new ProxyRequestBody()->prepare($request, $headers));
    }

    public function testRebuildsMultipartFieldsAndUploadedFile(): void
    {
        $headers = ['content-type' => ['multipart/form-data; boundary=old']];
        $file = new UploadedFile(__FILE__, 'original.php.txt', 'text/plain', test: true);
        $request = Request::create('/', 'POST', ['meta' => ['name' => 'demo']], files: ['files' => [$file]], server: [
            'CONTENT_TYPE' => 'multipart/form-data; boundary=old',
        ], content: '{"meta":{"name":"demo"}}');

        $body = new ProxyRequestBody()->prepare($request, $headers);
        $content = implode('', iterator_to_array($body));

        self::assertStringContainsString('multipart/form-data; boundary=', $headers['content-type'][0]);
        self::assertStringNotContainsString('boundary=old', $headers['content-type'][0]);
        self::assertStringContainsString('name="meta[name]"', $content);
        self::assertStringContainsString('name="files[0]"; filename="original.php.txt"', $content);
        self::assertStringContainsString(file_get_contents(__FILE__), $content);
    }
}
