<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Controller;

use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Product\ProductNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Application\Service\CategoryProduct\CategoryProductService;
use Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\AssignProductToCategory\AssignProductToCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\RemoveProductFromCategory\RemoveProductFromCategoryCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1', name: 'app.api.v1.catalog.category-product.', format: 'json', stateless: true)]
class CategoryProductController
{
    public function __construct(
        private readonly CategoryProductService $categoryProductService,
    ) {
    }

    #[Route(path: '/categories/{categoryId}/products/{productId}', name: 'assign_product', methods: ['PUT'])]
    public function assignProductToCategory(string $categoryId, string $productId): JsonResponse
    {
        try {
            $this->categoryProductService->assignProductToCategory(new AssignProductToCategoryCommand(
                categoryId: $categoryId,
                productId: $productId,
            ));
        } catch (CategoryNotFoundException) {
            return new JsonResponse(['error' => 'Category not found.'], JsonResponse::HTTP_NOT_FOUND);
        } catch (ProductNotFoundException) {
            return new JsonResponse(['error' => 'Product not found.'], JsonResponse::HTTP_NOT_FOUND);
        } catch (StoreMismatchException) {
            return new JsonResponse(['error' => 'Store mismatch between category and product.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route(path: '/categories/{categoryId}/products/{productId}', name: 'remove_product', methods: ['DELETE'], priority: 10)]
    public function removeProductFromCategory(string $categoryId, string $productId): JsonResponse
    {
        try {
            $this->categoryProductService->removeProductFromCategory(new RemoveProductFromCategoryCommand(
                categoryId: $categoryId,
                productId: $productId,
            ));
        } catch (CategoryNotFoundException) {
            return new JsonResponse(['error' => 'Category not found.'], JsonResponse::HTTP_NOT_FOUND);
        } catch (ProductNotFoundException) {
            return new JsonResponse(['error' => 'Product not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
