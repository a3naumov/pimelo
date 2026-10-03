<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Persistence\Doctrine\Fixture;

use App\Core\Catalog\Domain\Service\Category\CategorySlugGeneratorInterface;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class CategoryFixtures extends Fixture implements FixtureGroupInterface
{
    public const array LEAF_NUMBERS = [3, 4, 6, 7, 9, 10, 13, 14, 16, 17, 19, 20, 23, 24, 26, 27, 29, 30];

    private const array TREE = [
        'Electronics' => [
            'Computers' => ['Laptops', 'Desktop Computers'],
            'Phones' => ['Smartphones', 'Phone Accessories'],
            'Audio' => ['Headphones', 'Speakers'],
        ],
        'Clothing' => [
            'Women' => ['Dresses', 'Women Shoes'],
            'Men' => ['Shirts', 'Men Shoes'],
            'Children' => ['Kids Clothing', 'Kids Shoes'],
        ],
        'Home' => [
            'Kitchen' => ['Cookware', 'Kitchen Appliances'],
            'Furniture' => ['Tables', 'Chairs'],
            'Decor' => ['Lighting', 'Textiles'],
        ],
    ];

    public function __construct(private readonly CategorySlugGeneratorInterface $slugs)
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager): void
    {
        $number = 0;

        foreach (self::TREE as $rootName => $children) {
            $root = $this->persistCategory($manager, ++$number, $rootName);

            foreach ($children as $childName => $leaves) {
                $archived = 2 === $number + 1;
                $child = $this->persistCategory($manager, ++$number, $childName, $root->id, $archived);

                foreach ($leaves as $leafName) {
                    $this->persistCategory($manager, ++$number, $leafName, $child->id, $archived);
                }
            }
        }

        $manager->flush();
    }

    /**
     * @return list<string>
     */
    public static function getGroups(): array
    {
        return ['demo'];
    }

    /**
     * @throws InvalidArgumentException
     * @throws \BadMethodCallException
     */
    private function persistCategory(ObjectManager $manager, int $number, string $name, ?Uuid $parentId = null, bool $archived = false): Category
    {
        $category = new Category(
            Uuid::fromString(sprintf('01994731-0001-7000-8000-%012d', $number)),
            $name,
            $this->slugs->normalize($name),
            deletedAt: $archived ? new \DateTimeImmutable() : null,
            parentId: $parentId,
        );
        $manager->persist($category);
        $this->addReference('category-'.$number, $category);

        return $category;
    }
}
