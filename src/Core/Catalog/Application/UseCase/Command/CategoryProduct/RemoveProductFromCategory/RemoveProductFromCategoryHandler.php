<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\RemoveProductFromCategory;

use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Product\ProductNotFoundException;
use Pimelo\Core\Catalog\Domain\Aggregate\CategoryProduct;
use Pimelo\Core\Catalog\Domain\Repository\CategoryProductRepositoryInterface;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class RemoveProductFromCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryProductRepositoryInterface $categoryProductRepository,
    ) {
    }

    public function __invoke(RemoveProductFromCategoryCommand $command): void
    {
        $category = $this->categoryRepository->findById(ID::fromString($command->getCategoryId()));

        if (!$category) {
            throw new CategoryNotFoundException($command->getCategoryId());
        }

        $product = $this->productRepository->findById(ID::fromString($command->getProductId()));

        if (!$product) {
            throw new ProductNotFoundException($command->getProductId());
        }

        $categoryProduct = CategoryProduct::assign($category, $product);

        if (!$this->categoryProductRepository->exists($categoryProduct)) {
            return;
        }

        $this->categoryProductRepository->delete($categoryProduct);
    }
}
