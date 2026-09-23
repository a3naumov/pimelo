<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\General\Identity\Id;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(
    path: '/web/products/{productId}/categories',
    name: 'app.web.catalog.product_category.',
    requirements: ['productId' => '(?i:'.Requirement::UUID.')', 'categoryId' => '(?i:'.Requirement::UUID.')'],
    format: 'json',
    stateless: true,
)]
#[OA\Tag(name: 'Product categories')]
final class ProductCategoryController extends AbstractController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List categories linked to a product', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/CategoriesResponse')),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Head(summary: 'List categories linked to a product (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function list(string $productId): JsonResponse
    {
        try {
            $product = $this->productRepository->findById(Id::fromString($productId));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $categories = [];

            foreach ($this->productCategoryRepository->findCategories($product) as $category) {
                $categories[] = new CategoryResource($category->getId()->toString(), $category->getParentId()?->toString());
            }

            return $this->json(['categories' => $categories]);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{categoryId}', name: 'attach', methods: ['PUT'])]
    #[OA\Put(summary: 'Link a category to a product; repeated requests are idempotent', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'categoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function attach(string $productId, string $categoryId): Response
    {
        try {
            $product = $this->productRepository->findById(Id::fromString($productId));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $category = $this->categoryRepository->findById(Id::fromString($categoryId));

            if (null === $category) {
                return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->productCategoryRepository->attach($product, $category);

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    #[Route(path: '/{categoryId}', name: 'detach', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Unlink a category from a product; repeated requests are idempotent', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/InternalServerError', response: 500),
    ])]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'categoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function detach(string $productId, string $categoryId): Response
    {
        try {
            $product = $this->productRepository->findById(Id::fromString($productId));

            if (null === $product) {
                return $this->json(['error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $category = $this->categoryRepository->findById(Id::fromString($categoryId));

            if (null === $category) {
                return $this->json(['error' => 'Category not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->productCategoryRepository->detach($product, $category);

            return new Response(status: Response::HTTP_NO_CONTENT);
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
