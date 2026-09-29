<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryHandler;
use App\Core\Catalog\Application\UseCase\Category\DeleteCategory\DeleteCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\DeleteCategory\DeleteCategoryHandler;
use App\Core\Catalog\Application\UseCase\Category\DeleteCategoryPermanently\DeleteCategoryPermanentlyCommand;
use App\Core\Catalog\Application\UseCase\Category\DeleteCategoryPermanently\DeleteCategoryPermanentlyHandler;
use App\Core\Catalog\Application\UseCase\Category\GetCategory\GetCategoryHandler;
use App\Core\Catalog\Application\UseCase\Category\GetCategory\GetCategoryQuery;
use App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch\GetCategoryBranchHandler;
use App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch\GetCategoryBranchQuery;
use App\Core\Catalog\Application\UseCase\Category\ListCategories\ListCategoriesHandler;
use App\Core\Catalog\Application\UseCase\Category\ListCategories\ListCategoriesQuery;
use App\Core\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Core\Catalog\Application\UseCase\Category\RestoreCategory\RestoreCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\RestoreCategory\RestoreCategoryHandler;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Presenter\CategoryPresenter;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\CreateCategoryRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\ListCategoriesRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\MoveCategoryRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\CategoryBranch as CategoryBranchResource;
use App\Shared\General\Adapter\Symfony\Http\Error\ErrorResponder;
use App\Shared\General\Adapter\Symfony\Http\OpenApi\ErrorResponse;
use App\Shared\General\Identity\Id;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route(path: '/web/categories', name: 'app.web.catalog.category.', format: 'json', stateless: true)]
#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryPresenter $presenter,
        private readonly ErrorResponder $errors,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List root categories or immediate children of a parent', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['categories'], properties: [new OA\Property(property: 'categories', type: 'array', items: new OA\Items(ref: new Model(type: CategoryResource::class)))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'List root categories or immediate children (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Parent category not found; no response body.'),
        new OA\Response(response: 422, description: 'Invalid parent_id; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'parent_id', in: 'query', description: 'Parent UUID. Omit to list root categories.', schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted categories; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function list(
        Request $request,
        ListCategoriesHandler $listCategories,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?ListCategoriesRequest $filter = null,
    ): JsonResponse {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);
            $parentId = null === $filter?->parentId ? null : Id::fromString($filter->parentId);

            return $this->json(['categories' => $this->presenter->many($listCategories(new ListCategoriesQuery($parentId, $includeDeleted)))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/branch', name: 'branch', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get the complete path and sibling levels for a category', responses: [
        new OA\Response(response: 200, description: 'Complete branch, including root siblings and excluding children of the selected category.', content: new OA\JsonContent(ref: new Model(type: CategoryBranchResource::class))),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'Get a category branch (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Category not found; no response body.'),
        new OA\Response(response: 409, description: 'Invalid hierarchy; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted categories; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function branch(string $id, Request $request, GetCategoryBranchHandler $getCategoryBranch): JsonResponse
    {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);

            return $this->json($this->presenter->branch($getCategoryBranch(new GetCategoryBranchQuery(Id::fromString($id), $includeDeleted))));
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get a category', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['category'], properties: [new OA\Property(property: 'category', ref: new Model(type: CategoryResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'Get a category (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted categories; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function show(string $id, Request $request, GetCategoryHandler $getCategory): JsonResponse
    {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);

            return $this->json(['category' => $this->presenter->one($getCategory(new GetCategoryQuery(Id::fromString($id), $includeDeleted)))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a category with an optional parent; an omitted parent creates a root', responses: [
        new OA\Response(response: 201, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['category'], properties: [new OA\Property(property: 'category', ref: new Model(type: CategoryResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The requested parent is the category itself or a descendant, or the existing hierarchy contains a cycle.'),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    public function create(CreateCategoryHandler $createCategory, #[MapRequestPayload(acceptFormat: 'json')] ?CreateCategoryRequest $request = null): JsonResponse
    {
        try {
            $parentId = null === $request?->parentId ? null : Id::fromString($request->parentId);

            return $this->json(['category' => $this->presenter->one($createCategory(new CreateCategoryCommand($parentId)))], Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'move', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Move a category; use parent_id null to move it to the root', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['category'], properties: [new OA\Property(property: 'category', ref: new Model(type: CategoryResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The requested parent is the category itself or a descendant, or the existing hierarchy contains a cycle.'),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function move(
        string $id,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true],
        )]
        MoveCategoryRequest $request,
        MoveCategoryHandler $moveCategoryHandler,
    ): JsonResponse {
        try {
            $category = $moveCategoryHandler(new MoveCategoryCommand(
                Id::fromString($id),
                null === $request->parentId ? null : Id::fromString($request->parentId),
            ));

            return $this->json(['category' => $this->presenter->one($category)]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Soft-delete a category and its descendants, preserving product links', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function delete(string $id, DeleteCategoryHandler $deleteCategory): Response
    {
        try {
            $deleteCategory(new DeleteCategoryCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/restore', name: 'restore', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['POST'])]
    #[OA\Post(summary: 'Restore a deleted subtree and its ancestor chain, preserving product links', responses: [
        new OA\Response(response: 200, description: 'Restored category.', content: new OA\JsonContent(type: 'object', required: ['category'], properties: [new OA\Property(property: 'category', ref: new Model(type: CategoryResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409),
        new ErrorResponse(response: 500),
    ])]
    public function restore(string $id, RestoreCategoryHandler $restoreCategory): Response
    {
        try {
            return $this->json(['category' => $this->presenter->one($restoreCategory(new RestoreCategoryCommand(Id::fromString($id))))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/permanent', name: 'deletePermanently', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Permanently delete a deleted subtree and its links, preserving products', responses: [
        new OA\Response(response: 204, description: 'Deleted; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409),
        new ErrorResponse(response: 500),
    ])]
    public function deletePermanently(string $id, DeleteCategoryPermanentlyHandler $deleteCategoryPermanently): Response
    {
        try {
            $deleteCategoryPermanently(new DeleteCategoryPermanentlyCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }
}
