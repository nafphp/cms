<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Cms\Enums\Permission;
use Cms\Enums\Role;
use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202604300001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $this->createIdentitySchema($connection);
        $this->createInviteSchema($connection);
        $this->createTaxonomySchema($connection);
        $this->createArticleSchema($connection);
        $this->seedRolesAndPermissions($connection);
    }

    public function down(PDO $connection): void
    {
        foreach ([
            'article_review_actions',
            'article_versions',
            'article_tags',
            'articles',
            'tags',
            'categories',
            'user_invites',
            'role_permissions',
            'user_role',
            'permissions',
            'roles',
            'users',
        ] as $table) {
            $connection->exec("DROP TABLE IF EXISTS {$table}");
        }
    }

    private function createIdentitySchema(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(32) NULL,
                provider_id VARCHAR(128) NULL,
                firstname VARCHAR(80) NULL,
                lastname VARCHAR(120) NULL,
                email VARCHAR(190) NULL,
                username VARCHAR(120) NOT NULL,
                password TEXT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_users_username UNIQUE (username),
                CONSTRAINT uq_users_email UNIQUE (email)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_roles_name UNIQUE (name)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_permissions_name UNIQUE (name)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS user_role (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                PRIMARY KEY (user_id, role_id),
                CONSTRAINT fk_user_role_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_user_role_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
                CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
            )
        SQL);
    }

    private function createInviteSchema(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS user_invites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token_hash CHAR(64) NOT NULL,
                email VARCHAR(190) NOT NULL,
                roles_json JSON NOT NULL,
                created_by_user_id INT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT uq_user_invites_token_hash UNIQUE (token_hash),
                INDEX idx_user_invites_email (email),
                INDEX idx_user_invites_expires_at (expires_at),
                CONSTRAINT fk_user_invites_creator FOREIGN KEY (created_by_user_id)
                    REFERENCES users (id) ON DELETE SET NULL
            )
        SQL);
    }

    private function createTaxonomySchema(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_categories_slug UNIQUE (slug)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_tags_slug UNIQUE (slug)
            )
        SQL);
    }

    private function createArticleSchema(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NULL,
                author INT NULL,
                hide_lead_image_in_content TINYINT(1) NOT NULL DEFAULT 1,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
                CONSTRAINT fk_articles_author FOREIGN KEY (author) REFERENCES users (id) ON DELETE SET NULL
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS article_tags (
                article_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (article_id, tag_id),
                CONSTRAINT fk_article_tags_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
                CONSTRAINT fk_article_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS article_versions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                language VARCHAR(8) NOT NULL,
                version INT NOT NULL DEFAULT 1,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                slug VARCHAR(180) NOT NULL,
                title TEXT NOT NULL,
                teaser TEXT NULL,
                content JSON NOT NULL,
                publishedAt DATETIME NULL,
                scheduledAt DATETIME NULL,
                show_teaser TINYINT(1) NOT NULL DEFAULT 0,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_article_versions_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
                CONSTRAINT uq_article_language_version UNIQUE (article_id, language, version),
                INDEX idx_article_versions_slug_language (slug, language),
                INDEX idx_article_versions_status_language (status, language)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS article_review_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                article_version_id INT NOT NULL,
                language VARCHAR(8) NOT NULL,
                action VARCHAR(16) NOT NULL,
                comment TEXT NULL,
                reviewed_by INT NOT NULL,
                reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_article_review_actions_article
                    FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
                CONSTRAINT fk_article_review_actions_version
                    FOREIGN KEY (article_version_id) REFERENCES article_versions (id) ON DELETE CASCADE,
                CONSTRAINT fk_article_review_actions_user
                    FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT chk_article_review_actions_action
                    CHECK (action IN ('approve', 'decline')),
                INDEX idx_article_review_actions_version_reviewed_at (article_version_id, reviewed_at),
                INDEX idx_article_review_actions_article_lang_reviewed_at (article_id, language, reviewed_at)
            )
        SQL);
    }

    private function seedRolesAndPermissions(PDO $connection): void
    {
        $insertRole = $connection->prepare('INSERT IGNORE INTO roles (name) VALUES (:name)');
        foreach (Role::cases() as $role) {
            $insertRole->execute(['name' => $role->value]);
        }

        $insertPermission = $connection->prepare('INSERT IGNORE INTO permissions (name) VALUES (:name)');
        foreach (Permission::cases() as $permission) {
            $insertPermission->execute(['name' => $permission->value]);
        }

        $adminId = (int) $connection->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
        if ($adminId > 0) {
            $connection->exec(<<<SQL
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT {$adminId}, p.id FROM permissions p
            SQL);
        }
    }
}
