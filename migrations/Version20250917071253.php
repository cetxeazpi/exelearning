<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250917071253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_preferences KV table (no legacy data migration).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        // Create KV table
        if ($platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS system_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                pref_key VARCHAR(191) NOT NULL UNIQUE,
                value CLOB DEFAULT NULL,
                type VARCHAR(32) DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL
            )');
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS system_preferences (
                id INT AUTO_INCREMENT NOT NULL,
                pref_key VARCHAR(191) NOT NULL,
                value LONGTEXT DEFAULT NULL,
                type VARCHAR(32) DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL,
                UNIQUE INDEX UNIQ_SYSPREF_KEY (pref_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else { // PostgreSQL and others
            $this->addSql('CREATE TABLE IF NOT EXISTS system_preferences (
                id SERIAL NOT NULL,
                pref_key VARCHAR(191) NOT NULL UNIQUE,
                value TEXT DEFAULT NULL,
                type VARCHAR(32) DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        }

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS system_preferences');
    }
}
