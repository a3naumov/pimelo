<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Error;

use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\General\Adapter\Symfony\Http\Error\ExceptionStatusMapperInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class AttributeExceptionStatusMapper implements ExceptionStatusMapperInterface
{
    public function statusFor(\Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof AttributeNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof AttributeNotDeletedException => Response::HTTP_CONFLICT,
            default => null,
        };
    }
}
