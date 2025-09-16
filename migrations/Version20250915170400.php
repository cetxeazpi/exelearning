<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250915170400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create maintenance table to store global maintenance mode settings';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS maintenance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, enabled BOOLEAN NOT NULL DEFAULT 0, message VARCHAR(255) DEFAULT NULL, scheduled_end_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL)');
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS maintenance (id INT AUTO_INCREMENT NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, message VARCHAR(255) DEFAULT NULL, scheduled_end_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS maintenance (id SERIAL NOT NULL, enabled BOOLEAN NOT NULL DEFAULT FALSE, message VARCHAR(255) DEFAULT NULL, scheduled_end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY(id))');
        } else {
            // Generic ANSI fallback (may need adjustment per platform)
            $this->addSql('CREATE TABLE maintenance (id INTEGER NOT NULL, enabled BOOLEAN NOT NULL, message VARCHAR(255) NULL, scheduled_end_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, updated_by VARCHAR(180) NULL, PRIMARY KEY(id))');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS maintenance');
    }
}
