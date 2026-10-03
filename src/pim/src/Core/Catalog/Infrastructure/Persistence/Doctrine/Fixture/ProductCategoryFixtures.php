<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Persistence\Doctrine\Fixture;

use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ProductCategoryFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($number = 1; $number <= ProductFixtures::COUNT; ++$number) {
            $leafIndex = ($number - 1) % count(CategoryFixtures::LEAF_NUMBERS);
            $category = $this->getReference('category-'.CategoryFixtures::LEAF_NUMBERS[$leafIndex], Category::class);
            $product = $this->getReference('product-'.$number, Product::class);
            $manager->persist(new ProductCategory($product->id, $category->id));

            if (0 === $number % 3) {
                $rootNumber = intdiv($leafIndex, 6) * 10 + 1;
                $root = $this->getReference('category-'.$rootNumber, Category::class);
                $manager->persist(new ProductCategory($product->id, $root->id));
            }

            if (0 === $number % 200) {
                $manager->flush();
            }
        }

        $manager->flush();
    }

    /**
     * @return list<class-string<FixtureInterface>>
     */
    public function getDependencies(): array
    {
        return [CategoryFixtures::class, ProductFixtures::class];
    }

    /**
     * @return list<string>
     */
    public static function getGroups(): array
    {
        return ['demo'];
    }
}
