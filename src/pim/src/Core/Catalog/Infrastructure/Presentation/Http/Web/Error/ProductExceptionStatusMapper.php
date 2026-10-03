<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Error;

use App\Core\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Shared\General\Adapter\Symfony\Http\Error\ExceptionStatusMapperInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProductExceptionStatusMapper implements ExceptionStatusMapperInterface
{
    public function statusFor(\Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof ProductNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof ProductNotDeletedException,
            $exception instanceof ProductSkuAlreadyExistsException => Response::HTTP_CONFLICT,
            default => null,
        };
    }
}
