<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use DomainException;
use InvalidArgumentException;
use Naf\CMS\Page\Page;
use Naf\CMS\Page\PageFactory;
use PDO;
use RuntimeException;
use Throwable;

use function Naf\Database\database;
use function Naf\log;

final class PageRepository
{
    public function __construct(
        private ?PDO $connection = null,
        private ?PageFactory $factory = null,
    ) {
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
        } catch (Throwable $e) {
            log()->error('PageRepository.summaries failed', [
                'exception'      => $e,
                'template_count' => count($templates),
            ]);

            return [];
        }

        $labels = $this->templateLabels($templates);

        return array_map(static fn(array $row): array => [
            'id'         => (int) ($row['id'] ?? 0),
            'identifier' => (string) ($row['identifier'] ?? ''),
            'title'      => (string) ($row['title'] ?? ''),
            'path'       => (string) ($row['path'] ?? ''),
            'language'   => (string) ($row['language'] ?? ''),
            'status'     => (string) ($row['status'] ?? 'draft'),
            'template'   => $labels[(string) ($row['template_id'] ?? '')] ?? (string) ($row['template_id'] ?? ''),
            'templateId' => (string) ($row['template_id'] ?? ''),
            'themeId'    => (string) ($row['theme_id'] ?? ''),
            'updated'    => (string) (($row['updated_at'] ?? null) ?: ($row['created_at'] ?? '')),
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
        } catch (Throwable $e) {
            log()->error('PageRepository.find failed', [
                'exception' => $e,
                'id'        => $id,
            ]);

            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    public function findByPath(
        string $path,
        ?string $language = null,
        bool $publishedOnly = true,
        ?string $fallbackLanguage = null,
    ): ?array {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return null;
        }

        $path             = $this->normalizePath($path);
        $language         = $language === null ? null : strtolower(trim($language));
        $fallbackLanguage = $fallbackLanguage === null ? null : strtolower(trim($fallbackLanguage));

        try {
            $sql        = 'SELECT * FROM pages WHERE path = :path';
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

            if (
                !is_array($row)
                && $language !== null
                && $language !== ''
                && $fallbackLanguage !== null
                && $fallbackLanguage !== ''
                && $fallbackLanguage !== $language
            ) {
                $fallbackSql = 'SELECT * FROM pages WHERE path = :path AND language = :language';
                if ($publishedOnly) {
                    $fallbackSql .= " AND status = 'published'";
                }
                $fallbackSql .= ' LIMIT 1';
                $statement = $connection->prepare($fallbackSql);
                $statement->execute(['path' => $path, 'language' => $fallbackLanguage]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
            }

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

            if (!is_array($row) && $language !== null && $language !== '') {
                // Last resort: serve the path in whatever language exists rather
                // than 404. Routing matched this path, so a page is expected.
                $anySql = 'SELECT * FROM pages WHERE path = :path';
                if ($publishedOnly) {
                    $anySql .= " AND status = 'published'";
                }
                $anySql .= ' ORDER BY language ASC LIMIT 1';
                $statement = $connection->prepare($anySql);
                $statement->execute(['path' => $path]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            log()->error('PageRepository.findByPath failed', [
                'exception'      => $e,
                'path'           => $path,
                'language'       => $language,
                'published_only' => $publishedOnly,
            ]);

            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * Everything route registration needs from a stored page, and nothing else.
     *
     * Deliberately does not build Page objects: this runs before block types are
     * registered, and constructing block content that early loses it.
     *
     * @param array<int, string> $templateIds Templates that can actually render
     * @return array<int, array{id: string, path: string, published: bool}>
     */
    public function routeDescriptors(array $templateIds): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        $known = [];
        foreach ($templateIds as $templateId) {
            $templateId = trim((string) $templateId);
            if ($templateId !== '') {
                $known[$templateId] = true;
            }
        }

        // Routes are registered on every request, CLI included, so a database
        // without the schema yet must stay quiet instead of logging an error
        // before the first migration has run.
        if ($known === [] || !$this->tableExists()) {
            return [];
        }

        try {
            // Drafts are included on purpose: without a route an editor cannot
            // preview them at all. They are not readable through it — the page
            // built for a draft requires PAGES_VIEW, and visitors without that
            // permission never get the row loaded in the first place.
            $statement = $connection->query(
                "SELECT id, path, status, template_id
                 FROM pages
                 ORDER BY path ASC, language ASC",
            );
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('PageRepository.routeDescriptors failed', [
                'exception'      => $e,
                'template_count' => count($templateIds),
            ]);

            return [];
        }

        $descriptors = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!isset($known[(string) ($row['template_id'] ?? '')])) {
                continue;
            }

            $path       = $this->normalizePath((string) ($row['path'] ?? '/'));
            $descriptor = [
                'id'        => 'db.page.' . (int) ($row['id'] ?? 0),
                'path'      => $path,
                'published' => (string) ($row['status'] ?? 'draft') === 'published',
            ];

            // One route per path. A published translation wins over a draft that
            // happened to sort first, because it decides whether the path counts
            // as live for the home-page override and for collision reporting.
            if (!isset($descriptors[$path]) || ($descriptor['published'] && !$descriptors[$path]['published'])) {
                $descriptors[$path] = $descriptor;
            }
        }

        return array_values($descriptors);
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
            $rows      = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('PageRepository.pagesForRouting failed', [
                'exception'      => $e,
                'template_count' => count($templates),
                'published_only' => $publishedOnly,
            ]);

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
    public function pageByPath(
        string $path,
        array $templates,
        ?string $language = null,
        bool $publishedOnly = true,
        ?string $fallbackLanguage = null,
    ): ?Page {
        $row = $this->findByPath($path, $language, $publishedOnly, $fallbackLanguage);
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
        } catch (Throwable $e) {
            log()->error('PageRepository.findTranslations failed', [
                'exception'  => $e,
                'identifier' => $identifier,
            ]);

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
            throw new RuntimeException('No database connection available.');
        }

        $id         = (int) ($payload['id'] ?? 0);
        $identifier = $this->normalizeIdentifier((string) ($payload['identifier'] ?? ''));
        $path       = $this->normalizePath((string) ($payload['path'] ?? ''));
        $title      = trim((string) ($payload['title'] ?? ''));
        $language   = strtolower(trim((string) ($payload['language'] ?? '')));
        $status     = $this->normalizeStatus((string) ($payload['status'] ?? 'draft'));
        $templateId = trim((string) ($payload['template_id'] ?? $payload['templateId'] ?? ''));
        $themeId    = trim((string) ($payload['theme_id'] ?? $payload['themeId'] ?? ''));
        $layout     = trim((string) ($payload['layout'] ?? 'layouts.page')) ?: 'layouts.page';
        $blocks     = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
        $meta       = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        if ($title === '' || $templateId === '') {
            throw new InvalidArgumentException('Page title and template id are required.');
        }

        $params = [
            'identifier'  => $identifier,
            'path'        => $path,
            'title'       => $title,
            'language'    => $language,
            'status'      => $status,
            'template_id' => $templateId,
            'theme_id'    => $themeId === '' ? null : $themeId,
            'blocks_json' => json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_json'   => json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'layout'      => $layout,
        ];

        $pathOwnerId = $this->pathLanguageOwnerId($path, $language);
        if ($pathOwnerId !== null && $pathOwnerId !== $id) {
            throw new DomainException(sprintf(
                'Page path "%s" is already used for language "%s".',
                $path,
                $language,
            ));
        }

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

        $statement = $connection->prepare(<<<SQL
            INSERT INTO pages (identifier, path, title, language, status, template_id, theme_id, blocks_json, meta_json, layout, created_at, updated_at)
            VALUES (:identifier, :path, :title, :language, :status, :template_id, :theme_id, :blocks_json, :meta_json, :layout, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return (int) $connection->lastInsertId();
    }

    public function pathLanguageOwnerId(string $path, string $language): ?int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return null;
        }

        $statement = $connection->prepare(
            'SELECT id FROM pages WHERE path = :path AND language = :language LIMIT 1',
        );
        $statement->execute([
            'path'     => $this->normalizePath($path),
            'language' => strtolower(trim($language)),
        ]);
        $id = (int) ($statement->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
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
            'id'      => (int) ($row['id'] ?? 0),
            'themeId' => (string) ($row['theme_id'] ?? ''),
            'blocks'  => $this->decodeJson((string) ($row['blocks_json'] ?? '{}')),
            'meta'    => $this->decodeJson((string) ($row['meta_json'] ?? '{}')),
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
            throw new InvalidArgumentException('Page identifier cannot be empty.');
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
        } catch (Throwable $e) {
            log()->warning('PageRepository.decodeJson failed', [
                'exception' => $e,
            ]);

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Is the pages table there yet?
     *
     * Route registration runs before the first migration on a fresh install,
     * where querying a missing table is expected rather than an error.
     */
    private function tableExists(): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        try {
            $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                $statement = $connection->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'pages'");
                $statement->execute();

                return (bool) $statement->fetchColumn();
            }

            $statement = $connection->prepare('SHOW TABLES LIKE :table');
            $statement->execute(['table' => 'pages']);

            return (bool) $statement->fetchColumn();
        } catch (Throwable $e) {
            log()->warning(sprintf('PageRepository.tableExists failed: %s', $e->getMessage()), ['exception' => $e]);

            return false;
        }
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (Throwable $e) {
            log()->error('PageRepository.connection failed', [
                'exception' => $e,
            ]);

            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }

    private function factory(): PageFactory
    {
        return $this->factory ??= new PageFactory();
    }
}
