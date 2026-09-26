<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\CreateProductRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\UpdateProductRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;
use App\General\Adapter\Symfony\Http\OpenApi\ErrorResponse;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
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
        private readonly ProductRepositoryInterface $productRepository,
        private readonly IdGeneratorInterface $idGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException */
    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List products', responses: [
        new ErrorResponse(response: 400),
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['products'], properties: [new OA\Property(property: 'products', type: 'array', items: new OA\Items(ref: new Model(type: ProductResource::class)))])),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'List products (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 400, description: 'Invalid query parameters; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'deleted'], default: 'active'))]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->all()['status'] ?? 'active';

        if (!in_array($status, ['active', 'deleted'], true)) {
            return $this->json(['error' => 'Status must be active or deleted.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $products = [];

            foreach ($this->productRepository->findAll(deleted: 'deleted' === $status) as $product) {
                $products[] = new ProductResource(id: $product->id->toString(), sku: $product->sku, deletedAt: $product->deletedAt);
            }

            return $this->json(['products' => $products]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    /** @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException */
    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get a product', responses: [
        new ErrorResponse(response: 400),
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['product'], properties: [new OA\Property(property: 'product', ref: new Model(type: ProductResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'Get a product (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 400, description: 'Invalid query parameters; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', schema: new OA\Schema(type: 'integer', enum: [0, 1], default: 0))]
    public function show(string $id, Request $request): JsonResponse
    {
        $includeDeleted = $request->query->all()['include_deleted'] ?? '0';

        if (!in_array($includeDeleted, ['0', '1'], true)) {
            return $this->json(['error' => 'Include deleted must be 0 or 1.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $product = $this->productRepository->findById(Id::fromString($id), includeDeleted: '1' === $includeDeleted);

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'product' => new ProductResource(id: $product->id->toString(), sku: $product->sku, deletedAt: $product->deletedAt),
            ]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
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
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productRepository->save(new Product(id: $this->idGenerator->generate(), sku: $request->sku));

            return $this->json([
                'product' => new ProductResource(id: $product->id->toString(), sku: $product->sku, deletedAt: $product->deletedAt),
            ], Response::HTTP_CREATED);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'A product with this SKU already exists.'], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
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
    public function update(string $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateProductRequest $request): JsonResponse
    {
        try {
            $existing = $this->productRepository->findById(Id::fromString($id));

            if (null === $existing) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $product = $this->productRepository->save(new Product(id: $existing->id, sku: $request->sku));

            return $this->json([
                'product' => new ProductResource(id: $product->id->toString(), sku: $product->sku, deletedAt: $product->deletedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'A product with this SKU already exists.'], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Soft-delete a product, preserving its SKU and category links', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function delete(string $id): Response
    {
        try {
            $product = $this->productRepository->findById(Id::fromString($id));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->productRepository->delete($product);

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
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
    public function restore(string $id): JsonResponse
    {
        try {
            $product = $this->productRepository->restore(Id::fromString($id));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            return $this->json(['product' => new ProductResource(id: $product->id->toString(), sku: $product->sku, deletedAt: $product->deletedAt)]);
        } catch (ProductNotDeletedException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
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
    public function deletePermanently(string $id): Response
    {
        try {
            if (!$this->productRepository->deletePermanently(Id::fromString($id))) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (ProductNotDeletedException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    private function serverError(\Throwable $exception): JsonResponse
    {
        $this->logger->error('Unexpected catalog request failure.', ['exception' => $exception]);

        // @phpstan-ignore missingType.checkedException (The fixed HTTP 500 status is valid.)
        return new JsonResponse(
            data: ['error' => 'Internal server error.'],
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
