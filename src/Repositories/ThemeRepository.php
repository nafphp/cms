<?php

declare(strict_types=1);

namespace NixPHP\CMS\Repositories;

use NixPHP\CMS\Contracts\ThemeSourceInterface;
use NixPHP\CMS\Services\Theme\ThemeCodeNormalizer;
use NixPHP\CMS\Theme\Theme;
use PDO;
use function NixPHP\Database\database;
use function NixPHP\log;

final class ThemeRepository implements ThemeSourceInterface
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaries(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || !$this->tableExists()) {
            return [];
        }

        try {
            $rows = $connection->query('SELECT * FROM themes ORDER BY updated_at DESC, created_at DESC, label ASC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log()->error(sprintf('ThemeRepository.summaries failed: %s', $e->getMessage()), ['exception' => $e]);
            return [];
        }

        return array_map(fn(array $row): array => $this->summary($this->hydrate($row)), is_array($rows) ? $rows : []);
    }

    public function find(int|string $id): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || !$this->tableExists()) {
            return null;
        }

        try {
            if (is_int($id) || ctype_digit((string) $id)) {
                $statement = $connection->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
                $statement->execute(['id' => (int) $id]);
            } else {
                $statement = $connection->prepare('SELECT * FROM themes WHERE identifier = :identifier LIMIT 1');
                $statement->execute(['identifier' => Theme::slug((string) $id)]);
            }
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log()->error(sprintf('ThemeRepository.find failed: %s', $e->getMessage()), ['exception' => $e]);
            return null;
        }

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, Theme>
     */
    public function themes(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            // Avoid the former table-existence probe on every public page.
            // A missing schema is already handled by this repository boundary.
            $rows = $connection->query('SELECT * FROM themes ORDER BY label ASC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log()->error(sprintf('ThemeRepository.themes failed: %s', $e->getMessage()), ['exception' => $e]);
            return [];
        }

        $themes = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $theme = $this->toTheme($this->hydrate($row));
            if ($theme instanceof Theme) {
                $themes[] = $theme;
            }
        }

        return $themes;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('No database connection available.');
        }

        $id = (int) ($payload['id'] ?? 0);
        $label = trim((string) ($payload['label'] ?? ''));
        $identifier = Theme::slug((string) ($payload['identifier'] ?? $label));
        $surfaces = $this->normalizeSurfaces(is_array($payload['surfaces'] ?? null) ? $payload['surfaces'] : []);
        $normalizer = new ThemeCodeNormalizer();
        $css = $normalizer->normalize((string) ($payload['css'] ?? ''));

        if ($label === '' || $identifier === '') {
            throw new \InvalidArgumentException('Theme label and identifier are required.');
        }

        $params = [
            'identifier' => $identifier,
            'label' => $label,
            'surfaces_json' => json_encode($surfaces, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'css' => $css,
            // Custom JavaScript is intentionally disabled for database-backed themes in v0.0.1.
            'js' => '',
        ];

        if ($id > 0) {
            $statement = $connection->prepare(<<<SQL
                UPDATE themes
                SET identifier = :identifier,
                    label = :label,
                    surfaces_json = :surfaces_json,
                    css = :css,
                    js = :js,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($params + ['id' => $id]);
            return $id;
        }

        $existing = $connection->prepare('SELECT id FROM themes WHERE identifier = :identifier LIMIT 1');
        $existing->execute(['identifier' => $identifier]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $this->save(array_replace($payload, ['id' => $existingId]));
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO themes (identifier, label, surfaces_json, css, js, created_at, updated_at)
            VALUES (:identifier, :label, :surfaces_json, :css, :js, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return (int) $connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            ...$row,
            'id' => (int) ($row['id'] ?? 0),
            'surfaces' => $this->decodeJson((string) ($row['surfaces_json'] ?? '{}')),
            'cssPath' => '/css/themes/' . Theme::slug((string) ($row['identifier'] ?? '')) . '.css',
            'jsPath' => '',
        ];
    }

    /**
     * @param array<string, mixed> $theme
     * @return array<string, mixed>
     */
    private function summary(array $theme): array
    {
        $surfaces = is_array($theme['surfaces'] ?? null) ? $theme['surfaces'] : [];

        return [
            'id' => (int) ($theme['id'] ?? 0),
            'identifier' => (string) ($theme['identifier'] ?? ''),
            'label' => (string) ($theme['label'] ?? ''),
            'surfaces' => implode(', ', array_values($surfaces)),
            'surfaceCount' => count($surfaces),
            'cssPath' => (string) ($theme['cssPath'] ?? ''),
            'jsPath' => (string) ($theme['jsPath'] ?? ''),
            'updated' => (string) (($theme['updated_at'] ?? null) ?: ($theme['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toTheme(array $row): ?Theme
    {
        $identifier = Theme::slug((string) ($row['identifier'] ?? ''));
        if ($identifier === '') {
            return null;
        }

        $theme = Theme::create($identifier)->setLabel((string) ($row['label'] ?? $identifier));
        foreach ($this->normalizeSurfaces(is_array($row['surfaces'] ?? null) ? $row['surfaces'] : []) as $id => $label) {
            $theme->addSurface($id, $label);
        }

        if (trim((string) ($row['css'] ?? '')) !== '') {
            $theme->addStylesheet((string) ($row['cssPath'] ?? '/css/themes/' . $identifier . '.css'));
        }
        return $theme;
    }

    /**
     * @param array<string, mixed> $surfaces
     * @return array<string, string>
     */
    private function normalizeSurfaces(array $surfaces): array
    {
        $normalized = [];
        foreach ($surfaces as $id => $label) {
            $id = Theme::slug((string) $id);
            $label = trim((string) $label);
            if ($id !== '' && $label !== '') {
                $normalized[$id] = $label;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            log()->warning(sprintf('ThemeRepository.decodeJson failed: %s', $e->getMessage()), ['exception' => $e]);
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function tableExists(): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        try {
            $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                $statement = $connection->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'themes'");
                $statement->execute();
                return (bool) $statement->fetchColumn();
            }

            $statement = $connection->prepare('SHOW TABLES LIKE :table');
            $statement->execute(['table' => 'themes']);
            return (bool) $statement->fetchColumn();
        } catch (\Throwable $e) {
            log()->warning(sprintf('ThemeRepository.tableExists failed: %s', $e->getMessage()), ['exception' => $e]);
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
        } catch (\Throwable $e) {
            log()->error(sprintf('ThemeRepository.connection failed: %s', $e->getMessage()), ['exception' => $e]);
            return null;
        }

        return $connection instanceof PDO ? $this->connection = $connection : null;
    }
}
