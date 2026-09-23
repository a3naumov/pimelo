<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\Presentation\Http\Web\Resource\Product as ProductResource;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\CreateProductRequest;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List products', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/ProductsResponse')),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Head(summary: 'List products (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    public function list(): JsonResponse
    {
        try {
            $products = [];

            foreach ($this->productRepository->findAll() as $product) {
                $products[] = new ProductResource(id: $product->getId()->toString(), sku: $product->getSku());
            }

            return $this->json(['products' => $products]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get a product', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Head(summary: 'Get a product (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function show(string $id): JsonResponse
    {
        try {
            $product = $this->productRepository->findById(Id::fromString($id));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'product' => new ProductResource(id: $product->getId()->toString(), sku: $product->getSku()),
            ]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a product with a unique SKU', responses: [
        new OA\Response(response: 201, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
        new OA\Response(ref: '#/components/responses/SkuConflict', response: 409),
        new OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productRepository->save(new Product(id: $this->idGenerator->generate(), sku: $request->sku));

            return $this->json([
                'product' => new ProductResource(id: $product->getId()->toString(), sku: $product->getSku()),
            ], Response::HTTP_CREATED);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'A product with this SKU already exists.'], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete a product and its category links', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
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
