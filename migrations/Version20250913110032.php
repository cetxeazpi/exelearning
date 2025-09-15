<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250913110032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add github_accounts table to store OAuth tokens per user';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('github_accounts')) {
            // Table already exists, skip creating it
            return;
        }
        $table = $schema->createTable('github_accounts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('provider', 'string', ['length' => 50]);
        $table->addColumn('access_token_enc', 'text', ['notnull' => false]);
        $table->addColumn('refresh_token_enc', 'text', ['notnull' => false]);
        $table->addColumn('token_expires_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('github_login', 'string', ['length' => 190, 'notnull' => false]);
        $table->addColumn('github_id', 'string', ['length' => 190, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id'], 'idx_github_accounts_user');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('github_accounts')) {
            $schema->dropTable('github_accounts');
        }
    }
}
