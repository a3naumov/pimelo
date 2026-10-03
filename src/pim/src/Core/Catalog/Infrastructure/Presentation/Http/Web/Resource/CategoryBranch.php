<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['path', 'levels'])]
final readonly class CategoryBranch implements \JsonSerializable
{
    /**
     * @param list<Category>                                              $path
     * @param list<array{parent_id: ?string, categories: list<Category>}> $levels
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Category::class)))]
        public array $path,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['parent_id', 'categories'], properties: [
            new OA\Property(property: 'parent_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'categories', type: 'array', items: new OA\Items(ref: new Model(type: Category::class))),
        ]))]
        public array $levels,
    ) {
    }

    /**
     * @return array{
     *     path: list<Category>,
     *     levels: list<array{parent_id: ?string, categories: list<Category>}>,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'levels' => $this->levels,
        ];
    }
}
