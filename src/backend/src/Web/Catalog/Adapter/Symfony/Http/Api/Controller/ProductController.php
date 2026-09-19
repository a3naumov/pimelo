<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Http\Api\Controller;

use App\Web\Catalog\Http\Api\Resource\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/web/products', name: 'app.web.catalog.product.', format: 'json', stateless: true)]
final class ProductController extends AbstractController
{
    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'products' => [
                new Product(id: '1', sku: 'product-1'),
                new Product(id: '2', sku: 'product-2'),
                new Product(id: '3', sku: 'product-3'),
            ],
        ]);
    }
}
