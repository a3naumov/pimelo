<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Core\Catalog\Application\UseCase\Product\CreateProduct\CreateProductCommand;
use App\Core\Catalog\Application\UseCase\Product\CreateProduct\CreateProductHandler;
use App\Core\Catalog\Application\UseCase\Product\DeleteProduct\DeleteProductCommand;
use App\Core\Catalog\Application\UseCase\Product\DeleteProduct\DeleteProductHandler;
use App\Core\Catalog\Application\UseCase\Product\DeleteProductPermanently\DeleteProductPermanentlyCommand;
use App\Core\Catalog\Application\UseCase\Product\DeleteProductPermanently\DeleteProductPermanentlyHandler;
use App\Core\Catalog\Application\UseCase\Product\GetProduct\GetProductHandler;
use App\Core\Catalog\Application\UseCase\Product\GetProduct\GetProductQuery;
use App\Core\Catalog\Application\UseCase\Product\ListProducts\ListProductsHandler;
use App\Core\Catalog\Application\UseCase\Product\ListProducts\ListProductsQuery;
use App\Core\Catalog\Application\UseCase\Product\RestoreProduct\RestoreProductCommand;
use App\Core\Catalog\Application\UseCase\Product\RestoreProduct\RestoreProductHandler;
use App\Core\Catalog\Application\UseCase\Product\UpdateProduct\UpdateProductCommand;
use App\Core\Catalog\Application\UseCase\Product\UpdateProduct\UpdateProductHandler;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Presenter\ProductPresenter;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\CreateProductRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\UpdateProductRequest;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(path: '/web/products', name: 'app.web.catalog.product.', format: 'json', stateless: true)]
#[OA\Tag(name: 'Products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductPresenter $presenter,
        private readonly ErrorResponder $errors,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List products', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['products'], properties: [new OA\Property(property: 'products', type: 'array', items: new OA\Items(ref: new Model(type: ProductResource::class)))])),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'List products (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Only deleted selects deleted products; any other value selects active products.', schema: new OA\Schema(type: 'string', default: 'active'))]
    public function list(Request $request, ListProductsHandler $listProducts): JsonResponse
    {
        try {
            $deleted = 'deleted' === ($request->query->all()['status'] ?? null);

            return $this->json(['products' => $this->presenter->many($listProducts(new ListProductsQuery($deleted)))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get a product', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['product'], properties: [new OA\Property(property: 'product', ref: new Model(type: ProductResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'Get a product (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted products; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function show(string $id, Request $request, GetProductHandler $getProduct): JsonResponse
    {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);

            return $this->json([
                'product' => $this->presenter->one($getProduct(new GetProductQuery(Id::fromString($id), $includeDeleted))),
            ]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a product with a unique SKU', responses: [
        new OA\Response(response: 201, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['product'], properties: [new OA\Property(property: 'product', ref: new Model(type: ProductResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 409, description: 'A product with this SKU already exists, including soft-deleted products.', example: 'A product with this SKU already exists.'),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateProductRequest $request, CreateProductHandler $createProduct): JsonResponse
    {
        try {
            return $this->json([
                'product' => $this->presenter->one($createProduct(new CreateProductCommand($request->sku))),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'update', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Update a product SKU, preserving its identity and category links', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['product'], properties: [new OA\Property(property: 'product', ref: new Model(type: ProductResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'A product with this SKU already exists, including soft-deleted products.', example: 'A product with this SKU already exists.'),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function update(string $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateProductRequest $request, UpdateProductHandler $updateProduct): JsonResponse
    {
        try {
            return $this->json([
                'product' => $this->presenter->one($updateProduct(new UpdateProductCommand(Id::fromString($id), $request->sku))),
            ]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Soft-delete a product, preserving its SKU and category links', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function delete(string $id, DeleteProductHandler $deleteProduct): Response
    {
        try {
            $deleteProduct(new DeleteProductCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/restore', name: 'restore', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['POST'])]
    #[OA\Post(summary: 'Restore a deleted product with its SKU and category links', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['product'], properties: [new OA\Property(property: 'product', ref: new Model(type: ProductResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The product is already active.'),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function restore(string $id, RestoreProductHandler $restoreProduct): JsonResponse
    {
        try {
            return $this->json(['product' => $this->presenter->one($restoreProduct(new RestoreProductCommand(Id::fromString($id))))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/permanent', name: 'delete_permanently', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Permanently delete an archived product and its category links, freeing its SKU', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The product is still active.'),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function deletePermanently(string $id, DeleteProductPermanentlyHandler $deleteProductPermanently): Response
    {
        try {
            $deleteProductPermanently(new DeleteProductPermanentlyCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }
}
