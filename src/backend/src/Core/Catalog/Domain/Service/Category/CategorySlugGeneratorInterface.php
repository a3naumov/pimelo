<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Service\Category;

interface CategorySlugGeneratorInterface
{
    public function normalize(string $value): string;
}
