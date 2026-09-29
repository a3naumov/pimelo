<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Error;

use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\General\Adapter\Symfony\Http\Error\ExceptionStatusMapperInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class CategoryExceptionStatusMapper implements ExceptionStatusMapperInterface
{
    public function statusFor(\Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof CategoryNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof InvalidCategoryHierarchyException => Response::HTTP_CONFLICT,
            default => null,
        };
    }
}
