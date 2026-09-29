<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'sku', 'deleted_at'])]
final readonly class Product implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'string', format: 'uuid', example: '01994731-abcd-7000-8000-000000000001')]
        public string $id,
        #[OA\Property(type: 'string', minLength: 1, maxLength: 255, example: 'PRODUCT-001')]
        public string $sku,
        #[OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     sku: string,
     *     deleted_at: ?string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'deleted_at' => $this->deletedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
