<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Support\AuditLogRecord;

interface AuditSinkInterface
{
    /** @param array<string, mixed> $entry */
    public function record(array $entry): bool;

    /** @return list<AuditLogRecord> */
    public function latestRecords(int $limit = 100, ?string $subjectType = null): array;

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 100, ?string $subjectType = null): array;
}
