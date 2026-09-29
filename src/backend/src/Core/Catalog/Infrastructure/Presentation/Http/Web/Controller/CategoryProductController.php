<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Core\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory\AttachProductToCategoryCommand;
use App\Core\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory\AttachProductToCategoryHandler;
use App\Core\Catalog\Application\UseCase\CategoryProduct\DetachProductFromCategory\DetachProductFromCategoryCommand;
use App\Core\Catalog\Application\UseCase\CategoryProduct\DetachProductFromCategory\DetachProductFromCategoryHandler;
use App\Core\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts\ListCategoryProductsHandler;
use App\Core\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts\ListCategoryProductsQuery;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Presenter\ProductPresenter;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;
use App\Shared\General\Adapter\Symfony\Http\Error\ErrorResponder;
use App\Shared\General\Adapter\Symfony\Http\OpenApi\ErrorResponse;
use App\Shared\General\Identity\Id;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(
    path: '/web/categories/{categoryId}/products',
    name: 'app.web.catalog.category_product.',
    requirements: ['productId' => '(?i:'.Requirement::UUID.')', 'categoryId' => '(?i:'.Requirement::UUID.')'],
    format: 'json',
    stateless: true,
)]
#[OA\Tag(name: 'Category products')]
final class CategoryProductController extends AbstractController
{
    public function __construct(
        private readonly ProductPresenter $presenter,
        private readonly ErrorResponder $errors,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List active products directly linked to a category', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['products'], properties: [new OA\Property(property: 'products', type: 'array', items: new OA\Items(ref: new Model(type: ProductResource::class)))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'List active products directly linked to a category (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'categoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted categories; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function list(string $categoryId, Request $request, ListCategoryProductsHandler $listCategoryProducts): JsonResponse
    {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);

            return $this->json(['products' => $this->presenter->many($listCategoryProducts(new ListCategoryProductsQuery(Id::fromString($categoryId), $includeDeleted)))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{productId}', name: 'attach', methods: ['PUT'])]
    #[OA\Put(summary: 'Link a category to a product; repeated requests are idempotent', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'categoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function attach(string $productId, string $categoryId, AttachProductToCategoryHandler $attachProduct): Response
    {
        try {
            $attachProduct(new AttachProductToCategoryCommand(Id::fromString($productId), Id::fromString($categoryId)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{productId}', name: 'detach', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Unlink a category from a product; repeated requests are idempotent', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'categoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function detach(string $productId, string $categoryId, DetachProductFromCategoryHandler $detachProduct): Response
    {
        try {
            $detachProduct(new DetachProductFromCategoryCommand(Id::fromString($productId), Id::fromString($categoryId)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }
}
