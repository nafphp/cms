<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

use function Naf\Database\database;
use function Naf\log;

final class FormRepository
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function summaries(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $rows = $connection->query(<<<SQL
                SELECT f.id, f.identifier, f.title, f.status, f.fields_json, f.updated_at, f.created_at,
                    COUNT(s.id) AS submission_count
                FROM forms f
                LEFT JOIN form_submissions s ON s.form_id = f.id
                GROUP BY f.id, f.identifier, f.title, f.status, f.fields_json, f.updated_at, f.created_at
                ORDER BY f.updated_at DESC, f.created_at DESC, f.title ASC
            SQL)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.summaries failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return array_map(fn(array $row): array => [
            'id'          => (int) ($row['id'] ?? 0),
            'identifier'  => (string) ($row['identifier'] ?? ''),
            'title'       => (string) ($row['title'] ?? ''),
            'status'      => (string) ($row['status'] ?? 'draft'),
            'fields'      => count($this->decodeJson((string) ($row['fields_json'] ?? '[]'))),
            'submissions' => (int) ($row['submission_count'] ?? 0),
            'updated'     => (string) (($row['updated_at'] ?? null) ?: ($row['created_at'] ?? '')),
        ], is_array($rows) ? $rows : []);
    }

    /** @return array<int, array{label:string,value:string}> */
    public function choices(bool $publishedOnly = true): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $sql = 'SELECT identifier, title FROM forms';
            if ($publishedOnly) {
                $sql .= " WHERE status = 'published'";
            }
            $sql .= ' ORDER BY title ASC';
            $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.choices failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return array_map(static fn(array $row): array => [
            'label' => (string) ($row['title'] ?? $row['identifier'] ?? ''),
            'value' => (string) ($row['identifier'] ?? ''),
        ], is_array($rows) ? $rows : []);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM forms WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.find failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByIdentifier(string $identifier, bool $publishedOnly = true): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return null;
        }

        try {
            $sql = 'SELECT * FROM forms WHERE identifier = :identifier';
            if ($publishedOnly) {
                $sql .= " AND status = 'published'";
            }
            $sql .= ' LIMIT 1';
            $statement = $connection->prepare($sql);
            $statement->execute(['identifier' => $this->normalizeIdentifier($identifier)]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.findByIdentifier failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
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
        $title      = trim((string) ($payload['title'] ?? ''));
        $status     = $this->normalizeStatus((string) ($payload['status'] ?? 'draft'));
        $fields     = $this->normalizeFields(is_array($payload['fields'] ?? null) ? $payload['fields'] : []);
        $settings   = $this->normalizeSettings(is_array($payload['settings'] ?? null) ? $payload['settings'] : []);

        if ($title === '') {
            throw new InvalidArgumentException('Form title is required.');
        }

        $params = [
            'identifier'    => $identifier,
            'title'         => $title,
            'status'        => $status,
            'fields_json'   => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        if ($id > 0) {
            $statement = $connection->prepare(<<<SQL
                UPDATE forms
                SET identifier = :identifier,
                    title = :title,
                    status = :status,
                    fields_json = :fields_json,
                    settings_json = :settings_json,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($params + ['id' => $id]);

            return $id;
        }

        $existing = $connection->prepare('SELECT id FROM forms WHERE identifier = :identifier LIMIT 1');
        $existing->execute(['identifier' => $identifier]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $this->save(array_replace($payload, ['id' => $existingId]));
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO forms (identifier, title, status, fields_json, settings_json, created_at, updated_at)
            VALUES (:identifier, :title, :status, :fields_json, :settings_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return (int) $connection->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $submissions = $connection->prepare('DELETE FROM form_submissions WHERE form_id = :id');
        $submissions->execute(['id' => $id]);

        $statement = $connection->prepare('DELETE FROM forms WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<array-key, mixed> $meta
     */
    public function storeSubmission(int $formId, array $payload, array $meta = []): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $formId <= 0) {
            throw new RuntimeException('No form submission storage available.');
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO form_submissions (form_id, payload_json, meta_json, created_at)
            VALUES (:form_id, :payload_json, :meta_json, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute([
            'form_id'      => $formId,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_json'    => json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $connection->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function submissions(int $formId, int $limit = 50): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $formId <= 0) {
            return [];
        }

        try {
            $statement = $connection->prepare('SELECT * FROM form_submissions WHERE form_id = :form_id ORDER BY created_at DESC LIMIT :limit');
            $statement->bindValue('form_id', $formId, PDO::PARAM_INT);
            $statement->bindValue('limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.submissions failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return array_map(fn(array $row): array => [
            ...$row,
            'id'      => (int) ($row['id'] ?? 0),
            'form_id' => (int) ($row['form_id'] ?? 0),
            'payload' => $this->decodeJson((string) ($row['payload_json'] ?? '{}')),
            'meta'    => $this->decodeJson((string) ($row['meta_json'] ?? '{}')),
        ], is_array($rows) ? $rows : []);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<array-key, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            ...$row,
            'id'       => (int) ($row['id'] ?? 0),
            'fields'   => $this->decodeJson((string) ($row['fields_json'] ?? '[]')),
            'settings' => $this->decodeJson((string) ($row['settings_json'] ?? '{}')),
        ];
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/[^a-z0-9._-]+/', '-', $identifier) ?: '';
        $identifier = trim($identifier, '-._');
        if ($identifier === '') {
            throw new InvalidArgumentException('Form identifier cannot be empty.');
        }

        return $identifier;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<array-key, mixed>
     */
    private function normalizeSettings(array $settings): array
    {
        return [
            'titleKey'          => $this->normalizeTranslationKey((string) ($settings['titleKey'] ?? '')),
            'submitLabel'       => trim((string) ($settings['submitLabel'] ?? '')),
            'submitLabelKey'    => $this->normalizeTranslationKey((string) ($settings['submitLabelKey'] ?? '')),
            'successMessage'    => trim((string) ($settings['successMessage'] ?? '')),
            'successMessageKey' => $this->normalizeTranslationKey((string) ($settings['successMessageKey'] ?? '')),
            'actions'           => $this->normalizeActions(is_array($settings['actions'] ?? null) ? $settings['actions'] : []),
        ];
    }

    /**
     * @param array<int, mixed> $actions
     * @return array<array-key, mixed>
     */
    private function normalizeActions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $type = strtolower(trim((string) ($action['type'] ?? '')));
            if ($type === 'store') {
                $normalized[] = ['type' => 'store', 'enabled' => ($action['enabled'] ?? true) !== false];
                continue;
            }

            if ($type === 'webhook') {
                $url = trim((string) ($action['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                $method       = strtoupper(trim((string) ($action['method'] ?? 'POST')));
                $normalized[] = [
                    'type'        => 'webhook',
                    'enabled'     => ($action['enabled'] ?? true) !== false,
                    'url'         => $url,
                    'method'      => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? $method : 'POST',
                    'failOnError' => (bool) ($action['failOnError'] ?? false),
                ];
            }
        }

        return $normalized !== [] ? $normalized : [['type' => 'store', 'enabled' => true]];
    }

    /**
     * @param array<int, mixed> $fields
     * @return array<array-key, mixed>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $label      = trim((string) ($field['label'] ?? ''));
            $nameSource = trim((string) ($field['name'] ?? ''));
            if ($nameSource === '') {
                $nameSource = $label !== '' ? $label : 'field-' . ($index + 1);
            }
            $name         = $this->normalizeIdentifier($nameSource);
            $type         = strtolower(trim((string) ($field['type'] ?? 'text')));
            $normalized[] = [
                'name'           => $name,
                'label'          => $label !== '' ? $label : $name,
                'labelKey'       => $this->normalizeTranslationKey((string) ($field['labelKey'] ?? '')),
                'type'           => in_array($type, ['text', 'email', 'textarea', 'select', 'checkbox'], true) ? $type : 'text',
                'required'       => (bool) ($field['required'] ?? false),
                'placeholder'    => trim((string) ($field['placeholder'] ?? '')),
                'placeholderKey' => $this->normalizeTranslationKey((string) ($field['placeholderKey'] ?? '')),
                'options'        => array_values(array_filter(array_map('trim', is_array($field['options'] ?? null)
                    ? $field['options']
                    : explode("\n", (string) ($field['options'] ?? ''))))),
                'validators' => $this->normalizeValidators($field),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, array{rule:string,param:string}>
     */
    private function normalizeValidators(array $field): array
    {
        $validators = [];
        $source     = is_array($field['validators'] ?? null) ? $field['validators'] : [];

        foreach ($source as $validator) {
            if (!is_array($validator)) {
                continue;
            }

            $rule = strtolower(trim((string) ($validator['rule'] ?? '')));
            if (!in_array($rule, ['required', 'email', 'min', 'max'], true)) {
                continue;
            }

            $param = trim((string) ($validator['param'] ?? ''));
            if (in_array($rule, ['min', 'max'], true)) {
                $param = (string) max(0, (int) $param);
                if ($param === '0') {
                    continue;
                }
            } else {
                $param = '';
            }

            $validators[] = ['rule' => $rule, 'param' => $param];
        }

        if ($validators === []) {
            if ((bool) ($field['required'] ?? false)) {
                $validators[] = ['rule' => 'required', 'param' => ''];
            }
            if (strtolower(trim((string) ($field['validation'] ?? ''))) === 'email'
                || strtolower(trim((string) ($field['type'] ?? ''))) === 'email') {
                $validators[] = ['rule' => 'email', 'param' => ''];
            }
            if ((int) ($field['minLength'] ?? 0) > 0) {
                $validators[] = ['rule' => 'min', 'param' => (string) max(0, (int) $field['minLength'])];
            }
            if ((int) ($field['maxLength'] ?? 0) > 0) {
                $validators[] = ['rule' => 'max', 'param' => (string) max(0, (int) $field['maxLength'])];
            }
        }

        $seen = [];

        return array_values(array_filter($validators, static function (array $validator) use (&$seen): bool {
            $key = $validator['rule'] . ':' . $validator['param'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    private function normalizeTranslationKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9._-]+/', '.', $key) ?: '';
        $key = preg_replace('/[.]{2,}/', '.', $key) ?: '';

        return trim($key, '.-_');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            log()->warning(sprintf('FormRepository.decodeJson failed: %s', $e->getMessage()), ['exception' => $e]);

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
        } catch (Throwable $e) {
            log()->error(sprintf('FormRepository.connection failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }
}
