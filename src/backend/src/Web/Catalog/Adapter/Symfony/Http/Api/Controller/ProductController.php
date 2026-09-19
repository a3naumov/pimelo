<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Http\Api\Controller;

use App\Web\Catalog\Adapter\Symfony\Http\Api\Request\Product\CreateProductRequest;
use App\Web\Catalog\Entity\Product;
use App\Web\Catalog\Http\Api\Resource\Product as ProductResource;
use App\Web\Catalog\Persistence\Repository\ProductRepositoryInterface;
use App\Web\General\Identity\Id;
use App\Web\General\Identity\IdGeneratorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(path: '/web/products', name: 'app.web.catalog.product.', format: 'json', stateless: true)]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly IdGeneratorInterface $idGenerator,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    public function list(): JsonResponse
    {
        $products = [];

        foreach ($this->productRepository->findAll() as $product) {
            $products[] = new ProductResource(id: $product->getId()->toString(), sku: $product->getSku());
        }

        return $this->json(['products' => $products]);
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    public function show(string $id): JsonResponse
    {
        $product = $this->productRepository->findById(Id::fromString($id));

        if (null === $product) {
            return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'product' => new ProductResource(id: $product->getId()->toString(), sku: $product->getSku()),
        ]);
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productRepository->save(new Product(id: $this->idGenerator->generate(), sku: $request->sku));
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'A product with this SKU already exists.'], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'product' => new ProductResource(id: $product->getId()->toString(), sku: $product->getSku()),
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $product = $this->productRepository->findById(Id::fromString($id));

        if (null === $product) {
            return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->productRepository->delete($product);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
