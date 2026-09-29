<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\Error;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ErrorResponder
{
    /**
     * @param iterable<ExceptionStatusMapperInterface> $mappers
     */
    public function __construct(
        private LoggerInterface $logger,
        #[AutowireIterator('app.http.exception_status_mapper')]
        private iterable $mappers,
    ) {
    }

    public function respond(\Throwable $exception): JsonResponse
    {
        foreach ($this->mappers as $mapper) {
            $status = $mapper->statusFor($exception);

            if (null !== $status) {
                return $this->error($exception->getMessage(), $status);
            }
        }

        $this->logger->error('Unexpected request failure.', ['exception' => $exception]);

        return $this->error('Internal server error.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function error(string $message, int $status): JsonResponse
    {
        // @phpstan-ignore missingType.checkedException (Mappers and fallback use valid HTTP status codes.)
        return new JsonResponse(['error' => $message], $status);
    }
}
