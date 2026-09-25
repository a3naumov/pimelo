<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProductCategory::class)]
#[CoversClass(Category::class)]
#[CoversClass(Product::class)]
final class ProductCategoryTest extends KernelTestCase
{
    // ========================================================================
    // Schema: migrations and ORM retain indexes without generating foreign keys
    // ========================================================================

    public function testGeneratedAndMigratedSchemasKeepIndexesWithoutForeignKeys(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $generated = new SchemaTool($manager)->getSchemaFromMetadata($manager->getMetadataFactory()->getAllMetadata());
        $stored = $manager->getConnection()->createSchemaManager()->introspectSchema();

        $this->assertCatalogSchema($generated);
        $this->assertCatalogSchema($stored);

        foreach ([Product::class, Category::class, ProductCategory::class] as $class) {
            self::assertSame([], $manager->getClassMetadata($class)->getAssociationNames());
        }
    }

    private function assertCatalogSchema(Schema $schema): void
    {
        foreach (['product', 'category', 'product_category'] as $name) {
            self::assertSame([], $schema->getTable($name)->getForeignKeys());
            self::assertNotNull($schema->getTable($name)->getPrimaryKey());
        }

        self::assertTrue($schema->getTable('product')->getIndex('uniq_product_sku')->isUnique());
        self::assertSame(['parent_id'], $schema->getTable('category')->getIndex('IDX_64C19C1727ACA70')->getColumns());
        self::assertSame(['product_id'], $schema->getTable('product_category')->getIndex('IDX_CDFC73564584665A')->getColumns());
        self::assertSame(['category_id'], $schema->getTable('product_category')->getIndex('IDX_CDFC735612469DE2')->getColumns());
    }
}
