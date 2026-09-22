<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Http\Web\Controller;

use App\Catalog\Adapter\Symfony\Http\Web\Request\Category\MoveCategoryRequest;
use App\Catalog\Entity\Category;
use App\Catalog\Exception\Category\CategoryHasChildrenException;
use App\Catalog\Exception\Category\CategoryNotFoundException;
use App\Catalog\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Http\Web\Resource\Category as CategoryResource;
use App\Catalog\Persistence\Repository\CategoryRepositoryInterface;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route(path: '/web/categories', name: 'app.web.catalog.category.', format: 'json', stateless: true)]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly IdGeneratorInterface $idGenerator,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    public function list(): JsonResponse
    {
        $categories = [];

        foreach ($this->categoryRepository->findAll() as $category) {
            $categories[] = new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString());
        }

        return $this->json(['categories' => $categories]);
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    public function show(string $id): JsonResponse
    {
        $category = $this->categoryRepository->findById(Id::fromString($id));

        if (null === $category) {
            return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())]);
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        $category = $this->categoryRepository->save(new Category($this->idGenerator->generate()));

        return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())], Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'move', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['PATCH'])]
    public function move(string $id, #[MapRequestPayload(
        acceptFormat: 'json',
        serializationContext: [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true],
    )] MoveCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryRepository->move(
                Id::fromString($id),
                null === $request->parentId ? null : Id::fromString($request->parentId),
            );
        } catch (CategoryNotFoundException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidCategoryHierarchyException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())]);
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $category = $this->categoryRepository->findById(Id::fromString($id));

        if (null === $category) {
            return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->categoryRepository->delete($category);
        } catch (CategoryHasChildrenException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
