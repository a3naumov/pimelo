<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\DeleteProduct;

use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class DeleteProductHandler
{
    public function __construct(private ProductRepositoryInterface $products)
    {
    }

    /**
     * @throws ProductNotFoundException
     */
    public function __invoke(DeleteProductCommand $command): void
    {
        $product = $this->products->findById($command->id)
            ?? throw new ProductNotFoundException('Product not found.');

        $this->products->delete($product);
    }
}
