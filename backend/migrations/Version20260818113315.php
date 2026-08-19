<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818113315 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE passkeys (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            credential_id VARCHAR(255) NOT NULL,
            public_key LONGTEXT NOT NULL,
            user_handle VARCHAR(64) NOT NULL,
            counter BIGINT DEFAULT 0 NOT NULL,
            transports JSON NOT NULL,
            aaguid VARCHAR(36) NOT NULL,
            attestation_type VARCHAR(32) DEFAULT \'none\' NOT NULL,
            trust_path JSON NOT NULL,
            backup_eligible TINYINT DEFAULT NULL,
            backup_status TINYINT DEFAULT NULL,
            uv_initialized TINYINT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME DEFAULT NULL,
            user_id INT NOT NULL,
            UNIQUE INDEX UNIQ_42BD77282558A7A5 (credential_id),
            INDEX IDX_42BD7728A76ED395 (user_id),
            PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );

        $this->addSql('ALTER TABLE passkeys
            ADD CONSTRAINT FK_42BD7728A76ED395
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE passkeys DROP FOREIGN KEY FK_42BD7728A76ED395');
        $this->addSql('DROP TABLE passkeys');
    }
}
