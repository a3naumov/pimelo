<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\PreviewCategorySlug;

use App\Core\Catalog\Application\ReadModel\View\CategorySlugPreviewView;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Service\Category\CategorySlugAllocator;

final readonly class PreviewCategorySlugHandler
{
    public function __construct(private CategorySlugAllocator $slugs)
    {
    }

    /**
     * @throws InvalidCategoryDetailsException
     */
    public function __invoke(PreviewCategorySlugQuery $query): CategorySlugPreviewView
    {
        $base = $this->slugs->base($query->name, $query->slug);
        $suggestion = $this->slugs->suggestion($base, $query->excludeId);

        return new CategorySlugPreviewView($base, $base === $suggestion, $suggestion);
    }
}
