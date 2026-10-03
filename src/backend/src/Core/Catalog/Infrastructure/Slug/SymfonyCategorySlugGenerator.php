<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Slug;

use App\Core\Catalog\Domain\Service\Category\CategorySlugGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class SymfonyCategorySlugGenerator implements CategorySlugGeneratorInterface
{
    public function normalize(string $value): string
    {
        return new AsciiSlugger('en')->slug($value)->lower()->toString();
    }
}
