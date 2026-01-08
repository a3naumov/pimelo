<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107214843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
              id UUID NOT NULL,
              store_id UUID NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_PRODUCT_STORE_ID ON product (store_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product');
    }
}
