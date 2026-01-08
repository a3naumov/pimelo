<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Controller;

use Pimelo\Core\Catalog\Application\Service\ProductService;
use Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct\DeleteProductCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetAllProducts\GetAllProductsQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById\GetProductByIdQuery;
use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Core\Catalog\Presentation\Api\Request\Product\CreateProductRequest;
use Pimelo\Core\Catalog\Presentation\Api\Resource\Product\ProductResource;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/catalog/products', name: 'app.api.v1.catalog.product.', format: 'json', stateless: true)]
class ProductController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'products' => array_map(
                static fn (Product $product) => new ProductResource($product),
                $this->productService->getAllProducts(new GetAllProductsQuery()),
            ),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $product = $this->productService->findProductById(new GetProductByIdQuery(ID::fromString($id)));

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        return new JsonResponse(['products' => [new ProductResource($product)]]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
    ): JsonResponse {
        $id = $this->productService->createProduct(ID::fromString($request->getStoreId()));

        return new JsonResponse(['id' => $id], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->productService->deleteProduct(new DeleteProductCommand(ID::fromString($id)));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
