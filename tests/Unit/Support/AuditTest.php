<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Naf\CMS\Repositories\AuditLogRepository;
use Naf\CMS\Support\Audit;
use Naf\CMS\Support\AuditActorInfo;
use Naf\CMS\Support\AuditActorResolver;
use Naf\CMS\Support\AuditEntry;
use Tests\Support\DatabaseTestCase;
use function Naf\CMS\audit;
use function Naf\CMS\audit_log;

final class AuditTest extends DatabaseTestCase
{
    public function testAuditUsesInjectedActorResolver(): void
    {
        $this->createAuditSchema();

        $audit = new Audit(
            new AuditLogRepository($this->pdo),
            actorResolver: new class extends AuditActorResolver {
                public function resolve(): ?AuditActorInfo
                {
                    return new AuditActorInfo(42, 'Injected Editor', true);
                }
            },
        );

        self::assertTrue($audit->record(AuditEntry::create(
            'article.created',
            'article',
            '7',
            null,
            ['title' => 'Audit helpers'],
        )));

        $row = $this->fetchOne('SELECT actor_user_id, actor_label FROM audit_log LIMIT 1');

        self::assertSame(42, (int) ($row['actor_user_id'] ?? 0));
        self::assertSame('Injected Editor', $row['actor_label'] ?? null);
    }

    public function testAuditHelperReturnsSameInstanceRepeatedly(): void
    {
        self::assertSame(audit(), audit());
    }

    private function createAuditSchema(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
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
