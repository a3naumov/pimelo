<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\AssignProductToCategory;

use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Product\ProductNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Domain\Aggregate\CategoryProduct;
use Pimelo\Core\Catalog\Domain\Repository\CategoryProductRepositoryInterface;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class AssignProductToCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryProductRepositoryInterface $categoryProductRepository,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws ProductNotFoundException
     * @throws StoreMismatchException
     */
    public function __invoke(AssignProductToCategoryCommand $command): void
    {
        $category = $this->categoryRepository->findById(ID::fromString($command->getCategoryId()));

        if (!$category) {
            throw new CategoryNotFoundException($command->getCategoryId());
        }

        $product = $this->productRepository->findById(ID::fromString($command->getProductId()));

        if (!$product) {
            throw new ProductNotFoundException($command->getProductId());
        }

        if (!$category->getStoreId()->equals($product->getStoreId())) {
            throw new StoreMismatchException('Category and Product belong to different stores.');
        }

        $categoryProduct = CategoryProduct::assign($category, $product);

        if ($this->categoryProductRepository->exists($categoryProduct)) {
            return;
        }

        $this->categoryProductRepository->save($categoryProduct);
    }
}
