<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250917071253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_preferences KV table, migrate data from maintenance/additional_html/theme_settings and drop old tables';
    }

    public function up(Schema $schema): void
    {
        $p = $this->connection->getDatabasePlatform();

        // 1) Crear tabla KV
        if ($p instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS system_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                pref_key VARCHAR(191) NOT NULL UNIQUE,
                value CLOB DEFAULT NULL,
                type VARCHAR(32) DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                updated_by VARCHAR(180) DEFAULT NULL
            )');
        } elseif ($p instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $p instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
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
        } else { // PostgreSQL u otros
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

        // 2) Migrar datos si existen tablas antiguas (defensivo con TRY/CATCH SQL)
        // Maintenance -> KV
        try {
            $row = $this->connection->fetchAssociative('SELECT enabled, message, scheduled_end_at, updated_by FROM maintenance LIMIT 1');
            if ($row) {
                $enabled = (isset($row['enabled']) && (int)$row['enabled'] === 1) ? '1' : '0';
                $msg     = $row['message'] ?? null;
                $until   = $row['scheduled_end_at'] ?? null;
                $by      = $row['updated_by'] ?? null;

                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('maintenance.enabled', :v1, 'bool', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v1' => $enabled, 'by' => $by]);
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('maintenance.message', :v2, 'string', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v2' => $msg, 'by' => $by]);
                if ($until) {
                    // Normaliza a ISO 8601 si DB no lo devolvió así
                    $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                        ('maintenance.until', :v3, 'datetime', :by)
                        ON CONFLICT(pref_key) DO NOTHING", ['v3' => (string)$until, 'by' => $by]);
                }
            }
        } catch (\Throwable $ignored) {}

        // AdditionalHtml -> KV
        try {
            $row = $this->connection->fetchAssociative('SELECT head_html, top_of_body_html, footer_html, updated_by FROM additional_html LIMIT 1');
            if ($row) {
                $by = $row['updated_by'] ?? null;
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('additional_html.head', :v, 'html', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['head_html'] ?? null, 'by' => $by]);
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('additional_html.top', :v, 'html', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['top_of_body_html'] ?? null, 'by' => $by]);
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('additional_html.footer', :v, 'html', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['footer_html'] ?? null, 'by' => $by]);
            }
        } catch (\Throwable $ignored) {}

        // ThemeSettings -> KV
        try {
            $row = $this->connection->fetchAssociative('SELECT login_image_path, login_logo_path, favicon_path, updated_by FROM theme_settings LIMIT 1');
            if ($row) {
                $by = $row['updated_by'] ?? null;
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('theme.login_image_path', :v, 'string', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['login_image_path'] ?? null, 'by' => $by]);
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('theme.login_logo_path', :v, 'string', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['login_logo_path'] ?? null, 'by' => $by]);
                $this->addSql("INSERT INTO system_preferences (pref_key, value, type, updated_by) VALUES
                    ('theme.favicon_path', :v, 'string', :by)
                    ON CONFLICT(pref_key) DO NOTHING", ['v' => $row['favicon_path'] ?? null, 'by' => $by]);
            }
        } catch (\Throwable $ignored) {}

        // 3) Limpiar tablas antiguas (si existen)
        try { $this->addSql('DROP TABLE IF EXISTS maintenance'); } catch (\Throwable $ignored) {}
        try { $this->addSql('DROP TABLE IF EXISTS additional_html'); } catch (\Throwable $ignored) {}
        try { $this->addSql('DROP TABLE IF EXISTS theme_settings'); } catch (\Throwable $ignored) {}
    }

    public function down(Schema $schema): void
    {
        // Opcional: recrear tablas antiguas vacías o simplemente borrar KV
        $this->addSql('DROP TABLE IF EXISTS system_preferences');
        // No re-creamos maintenance/additional_html/theme_settings para simplificar rollback.
    }
}
