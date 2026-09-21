<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Http\Web\Request\Product;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    #[Assert\NotBlank(message: 'SKU must be a non-empty string.')]
    #[Assert\Length(max: 255, maxMessage: 'SKU must not exceed {{ limit }} characters.')]
    public string $sku;

    public function __construct(string $sku = '')
    {
        $this->sku = trim($sku);
    }
}
