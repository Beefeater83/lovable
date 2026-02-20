<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220195404 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL,
                           name VARCHAR(255) NOT NULL,
                           price DOUBLE PRECISION NOT NULL,
                           image_path VARCHAR(255) NOT NULL,
                            PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE products');
    }
}
