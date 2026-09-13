<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Enums\Role;
use Naf\CMS\Services\Security\CredentialService;
use Naf\CMS\Support\Slug;
use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use PDO;
use RuntimeException;
use function Naf\Database\database;

class FakeDataCommand extends AbstractCommand
{
    public const string NAME = 'seed:fake-data';

    /**
     * Demo article, one entry per language.
     *
     * Each slug is derived from its own localized title, which is what real
     * content looks like: translations get their own URL rather than sharing
     * one with a language suffix bolted on.
     *
     * @var array<string, array{slug: string, title: string, teaser: string, body: string}>
     */
    private const array DEMO_ARTICLE_VERSIONS = [
        'de' => [
            'slug' => 'nafcms-demo-geschichte',
            'title' => 'NafCMS Demo-Geschichte',
            'teaser' => 'Ein kurzer Beispielbeitrag fuer lokale Entwicklung und Tests.',
            'body' => 'Dies ist ein automatisch erzeugter Demo-Artikel. Du kannst ihn im Adminbereich bearbeiten.',
        ],
        'en' => [
            'slug' => 'nafcms-demo-story',
            'title' => 'NafCMS Demo Story',
            'teaser' => 'A short sample story used for local development and testing.',
            'body' => 'This is an auto-generated demo article. You can edit it in the admin area.',
        ],
    ];

    /**
     * Slugs earlier versions of this command seeded. Only used to recognise an
     * already seeded demo article.
     *
     * @var list<string>
     */
    private const array LEGACY_DEMO_ARTICLE_SLUGS = [
        'nafcms-demo-story-en',
    ];

    protected function configure(): void
    {
        $this
            ->setTitle('Seed Fake Data')
            ->setDescription('Insert demo articles, categories, tags, an admin user, and ensure the role/permission matrix is in place.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/naf seed:fake-data');
            $output->writeLine('  Idempotent: re-running updates demo content rather than duplicating.');
            return self::SUCCESS;
        }

        $pdo = database();
        if (!$pdo instanceof PDO) {
            $output->writeLine('Database connection not available. Run install:database first.', 'error');
            return self::ERROR;
        }

        $this->seedRoles($pdo);
        $this->seedPermissions($pdo);
        $this->seedRolePermissionMatrix($pdo);

        $userId = $this->upsertDemoUser($pdo);
        $this->assignRoleToUser($pdo, $userId, Role::ADMIN->value);

        $categoryId = $this->upsertCategory($pdo, 'City Walks');
        $tagIds = $this->upsertTags($pdo, ['demo', 'city', 'walk']);

        $articleId = $this->upsertDemoArticle($pdo, $userId, $categoryId);
        $this->upsertDemoArticleTags($pdo, $articleId, $tagIds);
        $this->upsertDemoArticleVersions($pdo, $articleId);

        $repaired = $this->repairBrokenArticleVersions($pdo);
        $recovered = $this->createMissingArticleVersions($pdo);

        $output->writeLine('Seed run complete.', 'ok');
        $output->writeLine(sprintf('  Demo user id:        %d (flo / test)', $userId));
        $output->writeLine(sprintf('  Demo article id:     %d', $articleId));
        $output->writeLine(sprintf('  Repaired versions:   %d', $repaired));
        $output->writeLine(sprintf('  Recovered articles:  %d', $recovered));

        return self::SUCCESS;
    }

    private function seedRoles(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO roles (name) VALUES (:name)');
        foreach (Role::cases() as $role) {
            $stmt->execute(['name' => $role->value]);
        }
    }

    private function seedPermissions(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO permissions (name) VALUES (:name)');
        foreach (Permission::cases() as $permission) {
            $stmt->execute(['name' => $permission->value]);
        }
    }

    private function seedRolePermissionMatrix(PDO $pdo): void
    {
        $matrix = [
            Role::ADMIN->value => Permission::cases(),
            Role::EDITOR->value => [
                Permission::ADMIN_ACCESS,
                Permission::SYSTEM_READ,
                Permission::USERS_VIEW,
                Permission::ARTICLES_VIEW,
                Permission::ARTICLES_CREATE,
                Permission::ARTICLES_EDIT,
                Permission::ARTICLES_REVIEW,
                Permission::ARTICLES_PUBLISH,
                Permission::ARTICLES_SCHEDULE,
                Permission::ARTICLES_ARCHIVE,
                Permission::ARTICLES_DELETE,
                Permission::MEDIA_VIEW,
                Permission::MEDIA_UPLOAD,
                Permission::MEDIA_EDIT,
                Permission::MEDIA_DELETE,
                Permission::QUEUE_READ,
            ],
            Role::AUTHOR->value => [
                Permission::ADMIN_ACCESS,
                Permission::ARTICLES_VIEW,
                Permission::ARTICLES_CREATE,
                Permission::ARTICLES_EDIT,
                Permission::MEDIA_VIEW,
                Permission::MEDIA_UPLOAD,
            ],
            Role::REVIEWER->value => [
                Permission::ADMIN_ACCESS,
                Permission::ARTICLES_VIEW,
                Permission::ARTICLES_REVIEW,
                Permission::ARTICLES_PUBLISH,
                Permission::ARTICLES_ARCHIVE,
                Permission::MEDIA_VIEW,
            ],
            Role::TRANSLATOR->value => [
                Permission::ADMIN_ACCESS,
                Permission::ARTICLES_VIEW,
                Permission::ARTICLES_EDIT,
                Permission::MEDIA_VIEW,
            ],
            Role::VIEWER->value => [
                Permission::ADMIN_ACCESS,
                Permission::ARTICLES_VIEW,
                Permission::SYSTEM_READ,
                Permission::QUEUE_READ,
            ],
        ];

        foreach ($matrix as $roleName => $permissions) {
            $roleId = $this->getRoleIdByName($pdo, $roleName);
            if ($roleId <= 0) {
                continue;
            }

            foreach ($permissions as $permission) {
                $permissionId = $this->getPermissionIdByName($pdo, $permission->value);
                if ($permissionId <= 0) {
                    continue;
                }

                $pdo->prepare(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:roleId, :permissionId)'
                )->execute([
                    'roleId' => $roleId,
                    'permissionId' => $permissionId,
                ]);
            }
        }
    }

    private function upsertDemoUser(PDO $pdo): int
    {
        $pdo->prepare(
            'INSERT INTO users (username, password, email, firstname, lastname)
             VALUES (:username, :password, :email, :firstname, :lastname)
             ON DUPLICATE KEY UPDATE
                password = VALUES(password),
                firstname = VALUES(firstname),
                lastname = VALUES(lastname),
                email = COALESCE(users.email, VALUES(email))'
        )->execute([
            'username' => 'flo',
            'password' => (new CredentialService())->hashPassword('test'),
            'email' => 'flo@example.test',
            'firstname' => 'Flo',
            'lastname' => 'Mustermann',
        ]);

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => 'flo']);
        $userId = (int) $stmt->fetchColumn();
        if ($userId <= 0) {
            throw new RuntimeException('Could not create or resolve demo user.');
        }

        return $userId;
    }

    private function assignRoleToUser(PDO $pdo, int $userId, string $roleName): void
    {
        $roleId = $this->getRoleIdByName($pdo, $roleName);
        if ($roleId <= 0) {
            return;
        }

        $pdo->prepare('INSERT IGNORE INTO user_role (user_id, role_id) VALUES (:userId, :roleId)')
            ->execute(['userId' => $userId, 'roleId' => $roleId]);
    }

    private function upsertCategory(PDO $pdo, string $name): int
    {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $categoryId = (int) $stmt->fetchColumn();
        if ($categoryId > 0) {
            return $categoryId;
        }

        $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)')
            ->execute(['name' => $name, 'slug' => Slug::fromString($name) ?: 'category']);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<int, string> $names
     * @return array<int, int>
     */
    private function upsertTags(PDO $pdo, array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $name]);
            $existing = (int) $stmt->fetchColumn();
            if ($existing > 0) {
                $ids[] = $existing;
                continue;
            }

            $pdo->prepare('INSERT INTO tags (name, slug) VALUES (:name, :slug)')
                ->execute(['name' => $name, 'slug' => Slug::fromString($name) ?: $name]);
            $ids[] = (int) $pdo->lastInsertId();
        }

        return $ids;
    }

    private function upsertDemoArticle(PDO $pdo, int $authorId, int $categoryId): int
    {
        // Any slug this demo article ever used, so re-running the command
        // updates the existing article instead of seeding a second one.
        $slugs = [
            ...array_column(self::DEMO_ARTICLE_VERSIONS, 'slug'),
            ...self::LEGACY_DEMO_ARTICLE_SLUGS,
        ];
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));

        $stmt = $pdo->prepare(
            'SELECT article_id
             FROM article_versions
             WHERE slug IN (' . $placeholders . ')
             ORDER BY version DESC
             LIMIT 1'
        );
        $stmt->execute(array_values($slugs));
        $articleId = (int) $stmt->fetchColumn();

        if ($articleId <= 0) {
            $pdo->prepare(
                'INSERT INTO articles (author, category_id, hide_lead_image_in_content)
                 VALUES (:authorId, :categoryId, 1)'
            )->execute(['authorId' => $authorId, 'categoryId' => $categoryId]);
            return (int) $pdo->lastInsertId();
        }

        $pdo->prepare('UPDATE articles SET author = :authorId, category_id = :categoryId WHERE id = :articleId')
            ->execute(['authorId' => $authorId, 'categoryId' => $categoryId, 'articleId' => $articleId]);

        return $articleId;
    }

    /**
     * @param array<int, int> $tagIds
     */
    private function upsertDemoArticleTags(PDO $pdo, int $articleId, array $tagIds): void
    {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (:articleId, :tagId)'
        );
        foreach ($tagIds as $tagId) {
            $insert->execute(['articleId' => $articleId, 'tagId' => $tagId]);
        }
    }

    private function upsertDemoArticleVersions(PDO $pdo, int $articleId): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        foreach (self::DEMO_ARTICLE_VERSIONS as $language => $version) {
            $this->upsertArticleVersion(
                $pdo,
                $articleId,
                $language,
                $version['slug'],
                $version['title'],
                $version['teaser'],
                $this->buildContentJson($version['body']),
                $now
            );
        }
    }

    private function upsertArticleVersion(
        PDO $pdo,
        int $articleId,
        string $language,
        string $slug,
        string $title,
        string $teaser,
        string $contentJson,
        string $publishedAt,
    ): void {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM article_versions
             WHERE article_id = :articleId AND language = :language
             ORDER BY version DESC
             LIMIT 1'
        );
        $stmt->execute(['articleId' => $articleId, 'language' => $language]);
        $existingVersionId = (int) $stmt->fetchColumn();

        if ($existingVersionId > 0) {
            $pdo->prepare(
                'UPDATE article_versions
                 SET slug = :slug,
                     status = :status,
                     title = :title,
                     teaser = :teaser,
                     content = :content,
                     show_teaser = 1,
                     publishedAt = :publishedAt
                 WHERE id = :id'
            )->execute([
                'slug' => $slug,
                'status' => 'published',
                'title' => $title,
                'teaser' => $teaser,
                'content' => $contentJson,
                'publishedAt' => $publishedAt,
                'id' => $existingVersionId,
            ]);
            return;
        }

        $nextVersionStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(version), 0) + 1
             FROM article_versions
             WHERE article_id = :articleId AND language = :language'
        );
        $nextVersionStmt->execute(['articleId' => $articleId, 'language' => $language]);
        $version = max(1, (int) $nextVersionStmt->fetchColumn());

        $pdo->prepare(
            'INSERT INTO article_versions
                (article_id, language, version, status, slug, title, teaser, show_teaser, content, publishedAt)
             VALUES
                (:articleId, :language, :version, :status, :slug, :title, :teaser, 1, :content, :publishedAt)'
        )->execute([
            'articleId' => $articleId,
            'language' => $language,
            'version' => $version,
            'status' => 'published',
            'slug' => $slug,
            'title' => $title,
            'teaser' => $teaser,
            'content' => $contentJson,
            'publishedAt' => $publishedAt,
        ]);
    }

    private function repairBrokenArticleVersions(PDO $pdo): int
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $fallbackContent = $this->buildContentJson('Recovered placeholder content.');
        $affected = 0;

        $affected += $pdo->exec(
            "UPDATE article_versions
             SET slug = CONCAT('article-', article_id, '-v', version, '-', language)
             WHERE slug IS NULL OR TRIM(slug) = ''"
        ) ?: 0;

        $affected += $pdo->exec(
            "UPDATE article_versions
             SET status = 'draft'
             WHERE status IS NULL OR TRIM(status) = ''"
        ) ?: 0;

        $affected += $pdo->exec(
            "UPDATE article_versions
             SET title = CONCAT('Recovered Article #', article_id, ' (', UPPER(language), ')')
             WHERE title IS NULL OR TRIM(title) = ''"
        ) ?: 0;

        $affected += $pdo->exec(
            "UPDATE article_versions
             SET teaser = 'Recovered teaser.'
             WHERE teaser IS NULL OR TRIM(teaser) = ''"
        ) ?: 0;

        $contentStmt = $pdo->prepare(
            "UPDATE article_versions
             SET content = :content
             WHERE content IS NULL OR TRIM(content) = ''"
        );
        $contentStmt->execute(['content' => $fallbackContent]);
        $affected += $contentStmt->rowCount();

        $publishedStmt = $pdo->prepare(
            'UPDATE article_versions SET publishedAt = :publishedAt WHERE publishedAt IS NULL'
        );
        $publishedStmt->execute(['publishedAt' => $now]);
        $affected += $publishedStmt->rowCount();

        return $affected;
    }

    private function createMissingArticleVersions(PDO $pdo): int
    {
        $stmt = $pdo->query(
            'SELECT a.id
             FROM articles a
             WHERE NOT EXISTS (SELECT 1 FROM article_versions av WHERE av.article_id = a.id)'
        );
        $articleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $created = 0;
        $publishedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        foreach ($articleIds as $rawArticleId) {
            $articleId = (int) $rawArticleId;
            if ($articleId <= 0) {
                continue;
            }

            $this->upsertArticleVersion(
                $pdo,
                $articleId,
                'de',
                sprintf('article-%d-de', $articleId),
                sprintf('Recovered Article #%d', $articleId),
                'Recovered teaser.',
                $this->buildContentJson('Recovered article content.'),
                $publishedAt
            );

            $created++;
        }

        return $created;
    }

    private function buildContentJson(string $text): string
    {
        $payload = [
            'time' => time() * 1000,
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => $text]],
            ],
            'version' => '2.30.7',
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{"time":0,"blocks":[],"version":"2.30.7"}';
    }

    private function getRoleIdByName(PDO $pdo, string $roleName): int
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        return (int) $stmt->fetchColumn();
    }

    private function getPermissionIdByName(PDO $pdo, string $permissionName): int
    {
        $stmt = $pdo->prepare('SELECT MIN(id) FROM permissions WHERE name = :name');
        $stmt->execute(['name' => $permissionName]);
        return (int) $stmt->fetchColumn();
    }
}
