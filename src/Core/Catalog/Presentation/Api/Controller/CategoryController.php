<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Controller;

use Pimelo\Core\Catalog\Application\Dto\Category\CategoryDto;
use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Application\Service\Category\CategoryService;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\AssignToCategory\AssignToCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\CreateCategory\CreateCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory\DeleteCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetAllCategories\GetAllCategoriesQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById\GetCategoryByIdQuery;
use Pimelo\Core\Catalog\Presentation\Api\Request\Category\CreateCategoryRequest;
use Pimelo\Core\Catalog\Presentation\Api\Request\Category\UpdateCategoryParentRequest;
use Pimelo\Core\Catalog\Presentation\Api\Resource\Category\CategoryResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/categories', name: 'app.api.v1.catalog.category.', format: 'json', stateless: true)]
class CategoryController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
    }

    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'categories' => array_map(
                static fn (CategoryDto $category) => new CategoryResource($category),
                $this->categoryService->getAllCategories(new GetAllCategoriesQuery()),
            ),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $category = $this->categoryService->findCategoryById(new GetCategoryByIdQuery($id));

        if (!$category) {
            throw new NotFoundHttpException('Category not found');
        }

        return new JsonResponse(['category' => new CategoryResource($category)]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateCategoryRequest $request,
    ): JsonResponse {
        try {
            $id = $this->categoryService->createCategory(new CreateCategoryCommand(
                storeId: $request->getStoreId(),
                parentId: $request->getParentId(),
            ));
        } catch (CategoryNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (StoreMismatchException $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['id' => $id], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->categoryService->deleteCategory(new DeleteCategoryCommand($id));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route(path: '/{id}/parent', name: 'assign', methods: ['PATCH'])]
    public function assign(
        #[MapRequestPayload] UpdateCategoryParentRequest $request,
        string $id,
    ): JsonResponse {
        try {
            $this->categoryService->assignToCategory(new AssignToCategoryCommand(
                categoryId: $id,
                parentCategoryId: $request->getParentId(),
            ));
        } catch (CategoryNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (StoreMismatchException $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
