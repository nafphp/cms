<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support;

use NixPHP\CMS\Support\AuditEntry;

final class AuditLogRecord
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $actorUserId,
        public readonly string $actorLabel,
        public readonly AuditEntry $entry,
        public readonly string $ipAddress = '',
        public readonly string $createdAt = '',
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        $document = self::decodeDocument((string) ($row['entry_json'] ?? ''));
        if ($document !== null) {
            $actor = is_array($document['actor'] ?? null) ? $document['actor'] : [];
            $event = is_array($document['event'] ?? null) ? $document['event'] : [];
            $request = is_array($document['request'] ?? null) ? $document['request'] : [];

            return new self(
                id: (int) ($row['id'] ?? 0),
                actorUserId: isset($actor['user_id']) ? (int) $actor['user_id'] : null,
                actorLabel: (string) ($actor['label'] ?? ''),
                entry: AuditEntry::create(
                    (string) ($event['action'] ?? ''),
                    (string) ($event['subject_type'] ?? ''),
                    (string) ($event['subject_key'] ?? ''),
                    $event['old_value'] ?? null,
                    $event['new_value'] ?? null,
                    is_array($event['meta'] ?? null) ? $event['meta'] : [],
                ),
                ipAddress: (string) ($request['ip_address'] ?? ''),
                createdAt: (string) ($row['created_at'] ?? $document['created_at'] ?? ''),
            );
        }

        return new self(
            id: (int) ($row['id'] ?? 0),
            actorUserId: $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            actorLabel: (string) ($row['actor_label'] ?? ''),
            entry: AuditEntry::create(
                (string) ($row['action'] ?? ''),
                (string) ($row['subject_type'] ?? ''),
                (string) ($row['subject_key'] ?? ''),
                self::decodeValue((string) ($row['old_value'] ?? 'null')),
                self::decodeValue((string) ($row['new_value'] ?? 'null')),
                self::arrayValue(self::decodeValue((string) ($row['meta_json'] ?? '[]'))),
            ),
            ipAddress: (string) ($row['ip_address'] ?? ''),
            createdAt: (string) ($row['created_at'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'actor_user_id' => $this->actorUserId,
            'actor_label' => $this->actorLabel,
            'action' => $this->entry->action,
            'subject_type' => $this->entry->subjectType,
            'subject_key' => $this->entry->subjectKey,
            'old_value' => $this->entry->oldValue,
            'new_value' => $this->entry->newValue,
            'meta' => $this->entry->meta,
            'ip_address' => $this->ipAddress,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeDocument(string $json): ?array
    {
        $decoded = self::decodeValue($json);

        return is_array($decoded) && isset($decoded['event']) ? $decoded : null;
    }

    private static function decodeValue(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
