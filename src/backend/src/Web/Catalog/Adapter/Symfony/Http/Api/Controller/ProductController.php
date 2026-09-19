<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Http\Api\Controller;

use App\Web\Catalog\Http\Api\Resource\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/web/products', name: 'app.web.catalog.product.', format: 'json', stateless: true)]
final class ProductController extends AbstractController
{
    private const array PRODUCTS = [
        '1' => 'product-1',
        '2' => 'product-2',
        '3' => 'product-3',
    ];

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    public function list(): JsonResponse
    {
        $products = [];

        foreach (self::PRODUCTS as $id => $sku) {
            $products[] = new Product(id: (string) $id, sku: $sku);
        }

        return $this->json(['products' => $products]);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET', 'HEAD'])]
    public function show(string $id): JsonResponse
    {
        if (!isset(self::PRODUCTS[$id])) {
            return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['product' => new Product(id: $id, sku: self::PRODUCTS[$id])]);
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $sku = $data['sku'] ?? null;

        if (!is_string($sku) || '' === trim($sku)) {
            return $this->json(['error' => 'SKU must be a non-empty string.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'product' => new Product(id: '4', sku: trim($sku)),
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        if (!isset(self::PRODUCTS[$id])) {
            return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
