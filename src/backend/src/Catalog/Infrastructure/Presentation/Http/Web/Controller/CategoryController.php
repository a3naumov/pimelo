<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryHasChildrenException;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\MoveCategoryRequest;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Psr\Log\LoggerInterface;
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
        private readonly MoveCategoryHandler $moveCategoryHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    public function list(): JsonResponse
    {
        try {
            $categories = [];

            foreach ($this->categoryRepository->findAll() as $category) {
                $categories[] = new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString());
            }

            return $this->json(['categories' => $categories]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    public function show(string $id): JsonResponse
    {
        try {
            $category = $this->categoryRepository->findById(Id::fromString($id));

            if (null === $category) {
                return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
            }

            return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        try {
            $category = $this->categoryRepository->save(new Category($this->idGenerator->generate()));

            return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())], Response::HTTP_CREATED);
        } catch (CategoryNotFoundException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidCategoryHierarchyException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'move', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['PATCH'])]
    public function move(
        string $id,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true],
        )]
        MoveCategoryRequest $request,
    ): JsonResponse {
        try {
            $category = ($this->moveCategoryHandler)(new MoveCategoryCommand(
                Id::fromString($id),
                null === $request->parentId ? null : Id::fromString($request->parentId),
            ));

            return $this->json(['category' => new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString())]);
        } catch (CategoryNotFoundException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidCategoryHierarchyException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        try {
            $category = $this->categoryRepository->findById(Id::fromString($id));

            if (null === $category) {
                return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->categoryRepository->delete($category);

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (CategoryHasChildrenException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    private function serverError(\Throwable $exception): JsonResponse
    {
        $this->logger->error('Unexpected catalog request failure.', ['exception' => $exception]);

        return new JsonResponse(
            data: ['error' => 'Internal server error.'],
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
