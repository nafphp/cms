<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use NixCMS\Page\Page;
use NixCMS\Page\PageFactory;
use PDO;
use function NixPHP\Database\database;

final class PageRepository
{
    public function __construct(
        private ?PDO $connection = null,
        private ?PageFactory $factory = null,
    )
    {
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @return array<int, array<string, mixed>>
     */
    public function summaries(array $templates = []): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $rows = $connection->query(<<<SQL
                SELECT id, identifier, path, title, language, status, template_id, theme_id, created_at, updated_at
                FROM pages
                ORDER BY updated_at DESC, created_at DESC, title ASC
            SQL)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $labels = $this->templateLabels($templates);

        return array_map(static fn(array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'identifier' => (string) ($row['identifier'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'path' => (string) ($row['path'] ?? ''),
            'language' => (string) ($row['language'] ?? ''),
            'status' => (string) ($row['status'] ?? 'draft'),
            'template' => $labels[(string) ($row['template_id'] ?? '')] ?? (string) ($row['template_id'] ?? ''),
            'templateId' => (string) ($row['template_id'] ?? ''),
            'themeId' => (string) ($row['theme_id'] ?? ''),
            'updated' => (string) (($row['updated_at'] ?? null) ?: ($row['created_at'] ?? '')),
        ], is_array($rows) ? $rows : []);
    }

    public function find(int $id): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    public function findByPath(string $path, ?string $language = null, bool $publishedOnly = true): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return null;
        }

        $path = $this->normalizePath($path);
        $language = $language === null ? null : strtolower(trim($language));

        try {
            $sql = 'SELECT * FROM pages WHERE path = :path';
            $parameters = ['path' => $path];
            if ($language !== null && $language !== '') {
                $sql .= ' AND language = :language';
                $parameters['language'] = $language;
            }
            if ($publishedOnly) {
                $sql .= " AND status = 'published'";
            }
            $sql .= ' ORDER BY language DESC LIMIT 1';
            $statement = $connection->prepare($sql);
            $statement->execute($parameters);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) && $language !== null && $language !== '') {
                $fallbackSql = 'SELECT * FROM pages WHERE path = :path AND language = :language';
                if ($publishedOnly) {
                    $fallbackSql .= " AND status = 'published'";
                }
                $fallbackSql .= ' LIMIT 1';
                $statement = $connection->prepare($fallbackSql);
                $statement->execute(['path' => $path, 'language' => '']);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /** @return array<int, Page> */
    public function pagesForRouting(array $templates, bool $publishedOnly = false): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $sql = $publishedOnly
                ? "SELECT * FROM pages WHERE status = 'published' ORDER BY path ASC, language ASC"
                : 'SELECT * FROM pages ORDER BY path ASC, language ASC';
            $statement = $connection->query($sql);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $definitions = [];
        foreach ($templates as $template) {
            if (is_array($template) && trim((string) ($template['id'] ?? '')) !== '') {
                $definitions[(string) $template['id']] = $template;
            }
        }

        $pages = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $definition = $definitions[(string) ($row['template_id'] ?? '')] ?? null;
            if (!is_array($definition)) {
                continue;
            }

            $pages[] = $this->factory()->fromStoredRow($this->hydrateRow($row), $definition);
        }

        return $pages;
    }

    /** @return array<int, Page> */
    public function publishedPages(array $templates): array
    {
        return $this->pagesForRouting($templates, true);
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     */
    public function pageByPath(string $path, array $templates, ?string $language = null, bool $publishedOnly = true): ?Page
    {
        $row = $this->findByPath($path, $language, $publishedOnly);
        if (!is_array($row)) {
            return null;
        }

        foreach ($templates as $template) {
            if (is_array($template) && (string) ($template['id'] ?? '') === (string) ($row['template_id'] ?? '')) {
                return $this->factory()->fromStoredRow($row, $template);
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function findTranslations(string $identifier): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $statement = $connection->prepare('SELECT * FROM pages WHERE identifier = :identifier ORDER BY language ASC');
            $statement->execute(['identifier' => $this->normalizeIdentifier($identifier)]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $translations = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $translations[(string) ($row['language'] ?? '')] = $this->hydrateRow($row);
        }

        return $translations;
    }

    /** @param array<string, mixed> $payload */
    public function save(array $payload): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('No database connection available.');
        }

        $id = (int) ($payload['id'] ?? 0);
        $identifier = $this->normalizeIdentifier((string) ($payload['identifier'] ?? ''));
        $path = $this->normalizePath((string) ($payload['path'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        $status = $this->normalizeStatus((string) ($payload['status'] ?? 'draft'));
        $templateId = trim((string) ($payload['template_id'] ?? $payload['templateId'] ?? ''));
        $themeId = trim((string) ($payload['theme_id'] ?? $payload['themeId'] ?? ''));
        $layout = trim((string) ($payload['layout'] ?? 'layouts.page')) ?: 'layouts.page';
        $blocks = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        if ($title === '' || $templateId === '') {
            throw new \InvalidArgumentException('Page title and template id are required.');
        }

        $params = [
            'identifier' => $identifier,
            'path' => $path,
            'title' => $title,
            'language' => $language,
            'status' => $status,
            'template_id' => $templateId,
            'theme_id' => $themeId === '' ? null : $themeId,
            'blocks_json' => json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_json' => json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'layout' => $layout,
        ];

        if ($id > 0) {
            $statement = $connection->prepare(<<<SQL
                UPDATE pages
                SET identifier = :identifier,
                    path = :path,
                    title = :title,
                    language = :language,
                    status = :status,
                    template_id = :template_id,
                    theme_id = :theme_id,
                    blocks_json = :blocks_json,
                    meta_json = :meta_json,
                    layout = :layout,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($params + ['id' => $id]);
            return $id;
        }

        $existing = $connection->prepare('SELECT id FROM pages WHERE path = :path AND language = :language LIMIT 1');
        $existing->execute(['path' => $path, 'language' => $language]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $this->save(array_replace($payload, ['id' => $existingId]));
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO pages (identifier, path, title, language, status, template_id, theme_id, blocks_json, meta_json, layout, created_at, updated_at)
            VALUES (:identifier, :path, :title, :language, :status, :template_id, :theme_id, :blocks_json, :meta_json, :layout, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return (int) $connection->lastInsertId();
    }

    public function publish(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $statement = $connection->prepare("UPDATE pages SET status = 'published', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $statement = $connection->prepare('DELETE FROM pages WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): array
    {
        return [
            ...$row,
            'id' => (int) ($row['id'] ?? 0),
            'themeId' => (string) ($row['theme_id'] ?? ''),
            'blocks' => $this->decodeJson((string) ($row['blocks_json'] ?? '{}')),
            'meta' => $this->decodeJson((string) ($row['meta_json'] ?? '{}')),
        ];
    }

    /** @param array<int, array<string, mixed>> $templates */
    private function templateLabels(array $templates): array
    {
        $labels = [];
        foreach ($templates as $template) {
            if (is_array($template)) {
                $labels[(string) ($template['id'] ?? '')] = (string) ($template['label'] ?? $template['id'] ?? '');
            }
        }

        return $labels;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/[^a-z0-9._-]+/', '-', $identifier) ?: '';
        $identifier = trim($identifier, '-._');

        if ($identifier === '') {
            throw new \InvalidArgumentException('Page identifier cannot be empty.');
        }

        return $identifier;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['draft', 'review', 'published', 'scheduled', 'archived'], true) ? $status : 'draft';
    }

    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (\Throwable) {
            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }

    private function factory(): PageFactory
    {
        return $this->factory ??= new PageFactory();
    }
}
