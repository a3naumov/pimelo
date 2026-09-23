<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Request\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['sku'])]
final readonly class CreateProductRequest
{
    #[Assert\NotBlank(message: 'SKU must be a non-empty string.')]
    #[OA\Property(description: 'Unique SKU. Leading and trailing whitespace is trimmed before validation and persistence.', minLength: 1, example: 'PRODUCT-001')]
    #[Assert\Length(max: 255, maxMessage: 'SKU must not exceed {{ limit }} characters.')]
    public string $sku;

    public function __construct(string $sku = '')
    {
        $this->sku = trim($sku);
    }
}
