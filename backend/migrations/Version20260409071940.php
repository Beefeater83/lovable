<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409071940 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token 
                            (token VARCHAR(64) NOT NULL,
                             expires_at DATETIME NOT NULL,
                              user_id INT NOT NULL, INDEX IDX_C74F2195A76ED395 (user_id),
                               PRIMARY KEY (token)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql('ALTER TABLE refresh_token
                            ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id)
                                REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('DROP TABLE refresh_token');
    }
}
