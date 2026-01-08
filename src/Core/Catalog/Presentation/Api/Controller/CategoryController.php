<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Controller;

use Pimelo\Core\Catalog\Application\Service\CategoryService;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory\DeleteCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetAllCategories\GetAllCategoriesQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById\GetCategoryByIdQuery;
use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Presentation\Api\Request\Category\CreateCategoryRequest;
use Pimelo\Core\Catalog\Presentation\Api\Resource\Category\CategoryResource;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/catalog/categories', name: 'app.api.v1.catalog.category.', format: 'json', stateless: true)]
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
                static fn (Category $category) => new CategoryResource($category),
                $this->categoryService->getAllCategories(new GetAllCategoriesQuery()),
            ),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $category = $this->categoryService->findCategoryById(new GetCategoryByIdQuery(ID::fromString($id)));

        if (!$category) {
            throw new NotFoundHttpException('Category not found');
        }

        return new JsonResponse(['categories' => [new CategoryResource($category)]]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateCategoryRequest $request,
    ): JsonResponse {
        $id = $this->categoryService->createCategory(ID::fromString($request->getStoreId()));

        return new JsonResponse(['id' => $id], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->categoryService->deleteCategory(new DeleteCategoryCommand(ID::fromString($id)));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
