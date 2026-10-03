<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the product-category many-to-many relation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product_category (
                product_id UUID NOT NULL,
                category_id UUID NOT NULL,
                PRIMARY KEY (product_id, category_id)
            )
            SQL);

        $this->addSql('CREATE INDEX IDX_CDFC73564584665A ON product_category (product_id)');
        $this->addSql('CREATE INDEX IDX_CDFC735612469DE2 ON product_category (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_category');
    }
}
