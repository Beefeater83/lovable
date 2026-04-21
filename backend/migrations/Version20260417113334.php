<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260417113334 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $adminId = $this->connection->fetchOne("
                SELECT id
                FROM users
                WHERE JSON_CONTAINS(roles, '\"ROLE_ADMIN\"')
                LIMIT 1
        ");

        $this->addSql('
                ALTER TABLE products
                ADD created_at DATETIME DEFAULT NULL,
                ADD updated_at DATETIME DEFAULT NULL,
                ADD user_id INT DEFAULT NULL
        ');
        $this->addSql('
                ALTER TABLE products
                ADD CONSTRAINT FK_B3BA5A5AA76ED395
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE SET NULL
        ');
        $this->addSql('CREATE INDEX IDX_B3BA5A5AA76ED395 ON products (user_id)');

        $this->addSql('
                UPDATE products
                SET created_at = :createdAt,
                    updated_at = :updatedAt
            ', [
            'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        if($adminId !== false && $adminId !== null) {
            $adminId = (int) $adminId;
            $this->addSql(sprintf(
                'UPDATE products SET user_id = %d',
                $adminId
            ));
        }

        $this->addSql('
            ALTER TABLE products
            MODIFY created_at DATETIME NOT NULL,
            MODIFY updated_at DATETIME NOT NULL
        ');

    }


    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5AA76ED395');
        $this->addSql('DROP INDEX IDX_B3BA5A5AA76ED395 ON products');
        $this->addSql('ALTER TABLE products DROP COLUMN user_id');
        $this->addSql('ALTER TABLE products DROP COLUMN created_at');
        $this->addSql('ALTER TABLE products DROP COLUMN updated_at');
    }
}
