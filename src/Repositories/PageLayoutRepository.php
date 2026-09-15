<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use InvalidArgumentException;
use Naf\CMS\Page\CustomLayoutNormalizer;
use PDO;
use RuntimeException;
use Stringable;
use Throwable;

use function Naf\Database\database;
use function Naf\log;

final class PageLayoutRepository
{
    private ?string $lastError = null;

    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $statement = $connection->query('SELECT * FROM page_layouts ORDER BY updated_at DESC, created_at DESC, name ASC');
            $rows      = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('PageLayoutRepository.all failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return array_map(fn(array $row): array => $this->hydrateRow($row), is_array($rows) ? $rows : []);
    }

    public function find(int $id): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM page_layouts WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('PageLayoutRepository.find failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return array_map(fn(array $layout): array => $this->toTemplate($layout), $this->all());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $this->lastError = null;
        $connection      = $this->connection();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('No database connection available.');
        }

        $id          = (int) ($payload['id'] ?? 0);
        $name        = trim((string) ($payload['name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $layout      = trim((string) ($payload['layout'] ?? 'layouts.page')) ?: 'layouts.page';
        $view        = trim((string) ($payload['view'] ?? 'page.templates.custom')) ?: 'page.templates.custom';
        $meta        = $this->filterMeta(is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
        $errors      = [];
        $slots       = (new CustomLayoutNormalizer())->normalize(
            is_array($payload['slots'] ?? null) ? $payload['slots'] : [],
            $errors,
        );

        if ($name === '') {
            throw new InvalidArgumentException('Layout name is required.');
        }

        if ($slots === [] || $errors !== []) {
            throw new InvalidArgumentException($errors[0] ?? 'Layout needs at least one slot.');
        }

        $identifier = $this->uniqueIdentifier(
            $this->normalizeIdentifier((string) ($payload['identifier'] ?? $name)),
            $id,
        );

        $params = [
            'identifier'  => $identifier,
            'name'        => $name,
            'description' => $description,
            'layout'      => $layout,
            'view'        => $view,
            'slots_json'  => json_encode($slots, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_json'   => json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        if ($id > 0 && $this->find($id) !== null) {
            $statement = $connection->prepare(<<<SQL
                UPDATE page_layouts
                SET identifier = :identifier,
                    name = :name,
                    description = :description,
                    layout = :layout,
                    view = :view,
                    slots_json = :slots_json,
                    meta_json = :meta_json,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($params + ['id' => $id]);

            return $this->find($id) ?? [];
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO page_layouts (identifier, name, description, layout, view, slots_json, meta_json, created_at, updated_at)
            VALUES (:identifier, :name, :description, :layout, :view, :slots_json, :meta_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return $this->find((int) $connection->lastInsertId()) ?? [];
    }

    public function delete(int $id): bool
    {
        $this->lastError = null;
        $connection      = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            $this->lastError = 'Invalid layout id.';

            return false;
        }

        if ($this->find($id) === null) {
            $this->lastError = 'Layout does not exist.';

            return false;
        }

        try {
            if ($this->isReferencedByPage($id)) {
                $this->lastError = 'Layout is still used by one or more pages.';

                return false;
            }

            $statement = $connection->prepare('DELETE FROM page_layouts WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $statement->rowCount() > 0;
        } catch (Throwable $e) {
            log()->error(sprintf('PageLayoutRepository.delete failed: %s', $e->getMessage()), ['exception' => $e]);
            $this->lastError = 'Layout could not be deleted.';

            return false;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public function toTemplate(array $layout): array
    {
        $id = (int) ($layout['id'] ?? 0);

        return [
            'id'                     => 'custom_layout.' . $id,
            'label'                  => (string) ($layout['name'] ?? 'Custom layout'),
            'kind'                   => 'custom',
            'layout'                 => (string) ($layout['layout'] ?? 'layouts.page'),
            'view'                   => (string) ($layout['view'] ?? 'page.templates.custom'),
            'description'            => (string) (($layout['description'] ?? '') ?: 'Saved custom layout preset.'),
            'blocks'                 => is_array($layout['slots'] ?? null) ? $layout['slots'] : [],
            'baseTemplateId'         => 'custom',
            'layoutPresetId'         => $id,
            'layoutPresetIdentifier' => (string) ($layout['identifier'] ?? ''),
        ];
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            // database() already returns the shared PDO. Calling connect() on it
            // hit PDO's static factory, which does not exist before PHP 8.4 and
            // would open a second, DSN-less connection after it, so this
            // repository never had a connection at all.
            $connection = database();
        } catch (Throwable $e) {
            log()->error(sprintf('PageLayoutRepository.connection failed: %s', $e->getMessage()), ['exception' => $e]);
            $connection = null;
        }

        return $this->connection = $connection instanceof PDO ? $connection : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            ...$row,
            'id'    => (int) ($row['id'] ?? 0),
            'slots' => $this->decodeJson((string) ($row['slots_json'] ?? '[]')),
            'meta'  => $this->decodeJson((string) ($row['meta_json'] ?? '{}')),
        ];
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/[^a-z0-9._-]+/', '-', $identifier) ?: '';
        $identifier = trim($identifier, '-._');

        if ($identifier === '') {
            throw new InvalidArgumentException('Layout identifier cannot be empty.');
        }

        return substr($identifier, 0, 160);
    }

    private function uniqueIdentifier(string $identifier, int $ignoreId = 0): string
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return $identifier;
        }

        $base  = $identifier;
        $index = 2;
        while ($this->identifierExists($identifier, $ignoreId)) {
            $suffix     = '-' . $index;
            $identifier = substr($base, 0, 160 - strlen($suffix)) . $suffix;
            $index++;
        }

        return $identifier;
    }

    private function identifierExists(string $identifier, int $ignoreId): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        $statement = $connection->prepare('SELECT id FROM page_layouts WHERE identifier = :identifier LIMIT 1');
        $statement->execute(['identifier' => $identifier]);
        $id = (int) ($statement->fetchColumn() ?: 0);

        return $id > 0 && $id !== $ignoreId;
    }

    private function isReferencedByPage(int $layoutId): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        $statement = $connection->query('SELECT meta_json FROM pages');
        $rows      = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $meta         = $this->decodeJson((string) ($row['meta_json'] ?? '{}'));
            $referencedId = (int) ($meta['layoutPresetId'] ?? $meta['custom_layout_ref'] ?? 0);
            if ($referencedId === $layoutId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pages reference page_layouts only through page meta. There is no foreign key;
     * deleting a referenced layout is rejected here so page migration stays explicit.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function filterMeta(array $meta): array
    {
        $filtered = [];

        foreach (['source', 'createdBy'] as $key) {
            $value = $meta[$key] ?? null;
            if (is_scalar($value) || $value instanceof Stringable) {
                $filtered[$key] = (string) $value;
            }
        }

        if (is_array($meta['tags'] ?? null)) {
            $tags = [];
            foreach ($meta['tags'] as $tag) {
                if (is_scalar($tag) || $tag instanceof Stringable) {
                    $tag = trim((string) $tag);
                    if ($tag !== '') {
                        $tags[] = $tag;
                    }
                }
            }
            if ($tags !== []) {
                $filtered['tags'] = array_values(array_unique($tags));
            }
        }

        return $filtered;
    }

    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            log()->warning(sprintf('PageLayoutRepository.decodeJson failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return is_array($data) ? $data : [];
    }
}
