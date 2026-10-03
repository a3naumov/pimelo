<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\General\Adapter\Symfony\Http\Error;

use App\Shared\General\Adapter\Symfony\Http\Error\ErrorResponder;
use App\Shared\General\Adapter\Symfony\Http\Error\ExceptionStatusMapperInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ErrorResponder::class)]
final class ErrorResponderTest extends TestCase
{
    // ========================================================================
    // Known errors: use the first mapper that recognizes the exception
    // ========================================================================

    public function testRespondsWithMappedStatusAndOriginalMessage(): void
    {
        $exception = new \RuntimeException('Resource not found.');
        $unknown = $this->createMock(ExceptionStatusMapperInterface::class);
        $unknown->expects(self::once())->method('statusFor')->with($exception)->willReturn(null);
        $known = $this->createMock(ExceptionStatusMapperInterface::class);
        $known->expects(self::once())->method('statusFor')->with($exception)->willReturn(Response::HTTP_NOT_FOUND);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $response = (new ErrorResponder($logger, [$unknown, $known]))->respond($exception);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(['error' => 'Resource not found.'], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // Unexpected errors: hide details in HTTP and retain them in the log
    // ========================================================================

    public function testLogsUnmappedExceptionAndReturnsSafeResponse(): void
    {
        $exception = new \RuntimeException('Sensitive details.');
        $mapper = $this->createStub(ExceptionStatusMapperInterface::class);
        $mapper->method('statusFor')->willReturn(null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Unexpected request failure.', ['exception' => $exception]);

        $response = (new ErrorResponder($logger, [$mapper]))->respond($exception);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(['error' => 'Internal server error.'], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }
}
