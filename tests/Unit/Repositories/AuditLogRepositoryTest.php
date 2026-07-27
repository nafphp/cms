<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use src\Repositories\AuditLogRepository;
use Tests\Support\DatabaseTestCase;
use function NixCMS\audit_log;

final class AuditLogRepositoryTest extends DatabaseTestCase
{
    public function testRecordStoresStructuredEntryJsonAndMapsItBack(): void
    {
        $this->createAuditSchema(withEntryJson: true);
        $repository = new AuditLogRepository($this->pdo);

        self::assertTrue($repository->record([
            'actor_user_id' => 7,
            'actor_label' => 'Ada Admin',
            'action' => 'article.created',
            'subject_type' => 'article',
            'subject_key' => '42',
            'old_value' => null,
            'new_value' => ['title' => 'Launch Notes'],
            'meta' => ['password' => '[redacted]'],
            'ip_address' => '127.0.0.1',
        ]));

        $row = $this->fetchOne('SELECT entry_json FROM audit_log LIMIT 1');
        self::assertIsString($row['entry_json'] ?? null);
        self::assertStringContainsString('"schema":"nixcms.audit.v1"', (string) $row['entry_json']);

        $record = $repository->latestRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame(7, $record->actorUserId);
        self::assertSame('Ada Admin', $record->actorLabel);
        self::assertSame('article.created', $record->entry->action);
        self::assertSame('article', $record->entry->subjectType);
        self::assertSame('42', $record->entry->subjectKey);
        self::assertSame(['title' => 'Launch Notes'], $record->entry->newValue);
        self::assertSame(['password' => '[redacted]'], $record->entry->meta);
    }

    public function testRecordStillWorksWithLegacyAuditSchema(): void
    {
        $this->createAuditSchema(withEntryJson: false);
        $repository = new AuditLogRepository($this->pdo);

        self::assertTrue($repository->record([
            'actor_user_id' => null,
            'actor_label' => 'System',
            'action' => 'setting.updated',
            'subject_type' => 'setting',
            'subject_key' => 'app.name',
            'old_value' => 'Old',
            'new_value' => 'New',
            'meta' => [],
            'ip_address' => '',
        ]));

        $record = $repository->latestRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame('setting.updated', $record->entry->action);
        self::assertSame('Old', $record->entry->oldValue);
        self::assertSame('New', $record->entry->newValue);
    }

    private function createAuditSchema(bool $withEntryJson): void
    {
        $entryColumn = $withEntryJson ? 'entry_json TEXT NULL,' : '';
        $this->createSchema(<<<SQL
            CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                {$entryColumn}
                actor_user_id INTEGER NULL,
                actor_label VARCHAR(190) NOT NULL,
                action VARCHAR(120) NOT NULL,
                subject_type VARCHAR(120) NOT NULL,
                subject_key VARCHAR(190) NOT NULL,
                old_value TEXT NOT NULL,
                new_value TEXT NOT NULL,
                meta_json TEXT NOT NULL,
                ip_address VARCHAR(64) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }
}
