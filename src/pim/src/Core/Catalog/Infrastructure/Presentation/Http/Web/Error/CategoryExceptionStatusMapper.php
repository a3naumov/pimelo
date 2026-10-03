<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Error;

use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Shared\General\Adapter\Symfony\Http\Error\ExceptionStatusMapperInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class CategoryExceptionStatusMapper implements ExceptionStatusMapperInterface
{
    public function statusFor(\Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof CategoryNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof InvalidCategoryHierarchyException => Response::HTTP_CONFLICT,
            $exception instanceof CategorySlugConflictException => Response::HTTP_CONFLICT,
            $exception instanceof InvalidCategoryDetailsException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => null,
        };
    }
}
