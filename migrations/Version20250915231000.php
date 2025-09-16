<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250915231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create additional_html table to store per-site HTML injections';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('additional_html')) {
            return; // table already exists
        }

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $this->addSql('CREATE TABLE additional_html (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                head_html CLOB DEFAULT NULL,
                top_of_body_html CLOB DEFAULT NULL,
                footer_html CLOB DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL
            )');
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $this->addSql('CREATE TABLE additional_html (
                id INT AUTO_INCREMENT NOT NULL,
                head_html LONGTEXT DEFAULT NULL,
                top_of_body_html LONGTEXT DEFAULT NULL,
                footer_html LONGTEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else { // PostgreSQL and others
            $this->addSql('CREATE TABLE additional_html (
                id SERIAL NOT NULL,
                head_html TEXT DEFAULT NULL,
                top_of_body_html TEXT DEFAULT NULL,
                footer_html TEXT DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        }
    }

    public function down(Schema $schema): void
    {
        try {
            $this->addSql('DROP TABLE additional_html');
        } catch (\Throwable $e) {
            // ignore for platforms not supporting it or if already dropped
        }
    }
}

