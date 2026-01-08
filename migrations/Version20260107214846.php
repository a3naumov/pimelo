<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107214846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create category_product many-to-many join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE category_product (
              category_id UUID NOT NULL,
              product_id UUID NOT NULL,
              PRIMARY KEY(category_id, product_id)
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_CATEGORY_PRODUCT_CATEGORY_ID ON category_product (category_id)');
        $this->addSql('CREATE INDEX IDX_CATEGORY_PRODUCT_PRODUCT_ID ON category_product (product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category_product');
    }
}
