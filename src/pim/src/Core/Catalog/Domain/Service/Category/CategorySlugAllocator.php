<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Service\Category;

use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Shared\General\Identity\Id;

final readonly class CategorySlugAllocator
{
    public function __construct(
        private CategorySlugGeneratorInterface $generator,
        private CategoryRepositoryInterface $categories,
    ) {
    }

    /**
     * @throws InvalidCategoryDetailsException
     */
    public function base(string $name, ?string $customSlug): string
    {
        $custom = null !== $customSlug && '' !== trim($customSlug);
        $slug = $this->generator->normalize($custom ? $customSlug : $name);

        if ($custom && ('' === $slug || strlen($slug) > 255)) {
            throw new InvalidCategoryDetailsException('The normalized category slug must contain between 1 and 255 characters.');
        }

        return $custom ? $slug : rtrim(substr('' === $slug ? 'category' : $slug, 0, 255), '-');
    }

    public function suggestion(string $base, ?Id $excludeId = null): string
    {
        $candidate = $base;
        $suffix = 0;

        while ($this->categories->slugExists($candidate, $excludeId)) {
            $ending = '-'.++$suffix;
            $candidate = rtrim(substr($base, 0, 255 - strlen($ending)), '-').$ending;
        }

        return $candidate;
    }

    /**
     * @throws InvalidCategoryDetailsException
     * @throws CategorySlugConflictException
     */
    public function allocate(string $name, ?string $customSlug = null, ?Id $excludeId = null, bool $allowSuffix = false): string
    {
        $base = $this->base($name, $customSlug);
        $suggestion = $this->suggestion($base, $excludeId);

        if (null !== $customSlug && '' !== trim($customSlug) && !$allowSuffix && $base !== $suggestion) {
            throw new CategorySlugConflictException();
        }

        return $suggestion;
    }
}
