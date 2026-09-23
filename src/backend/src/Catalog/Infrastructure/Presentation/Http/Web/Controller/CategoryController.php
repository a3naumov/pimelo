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
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route(path: '/web/categories', name: 'app.web.catalog.category.', format: 'json', stateless: true)]
#[OA\Tag(name: 'Categories')]
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
    #[OA\Get(summary: 'List categories and their parent IDs', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/CategoriesResponse')),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Head(summary: 'List categories and their parent IDs (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
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
    #[OA\Get(summary: 'Get a category', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/CategoryResponse')),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Head(summary: 'Get a category (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
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
    #[OA\Post(summary: 'Create a root category without a request body', responses: [
        new OA\Response(response: 201, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/CategoryResponse')),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/HierarchyConflict', response: 409),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
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
    #[OA\Patch(summary: 'Move a category; use parent_id null to move it to the root', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/CategoryResponse')),
        new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/HierarchyConflict', response: 409),
        new OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
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
    #[OA\Delete(summary: 'Delete a category without children and remove its product links', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/CategoryHasChildren', response: 409),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
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
