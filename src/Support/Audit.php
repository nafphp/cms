<?php

declare(strict_types=1);

namespace src\Support;

use src\Repositories\AuditLogRepository;
use src\Support\AuditActorResolver;
use src\Support\AuditEntry;
use src\Support\AuditRedactor;
use function NixPHP\request;

final class Audit
{
    public function __construct(
        private readonly ?AuditLogRepository $repository = null,
        private readonly ?AuditRedactor $redactor = null,
        private readonly ?AuditActorResolver $actorResolver = null,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function record(
        AuditEntry|string $action,
        ?string $subjectType = null,
        ?string $subjectKey = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        array $meta = [],
    ): bool {
        $entry = $action instanceof AuditEntry
            ? $action
            : AuditEntry::create(
                $action,
                (string) $subjectType,
                (string) $subjectKey,
                $oldValue,
                $newValue,
                $meta,
            );

        $actor = $this->actorResolver()->resolve();

        return $this->repository()->record($this->redactEntry($entry) + [
            'actor_user_id' => $actor?->id,
            'actor_label' => $actor?->label ?? 'System',
            'ip_address' => $this->ipAddress(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function redactEntry(AuditEntry $entry): array
    {
        $redactor = $this->redactor();

        return [
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_key' => $entry->subjectKey,
            'old_value' => $redactor->redact($entry->oldValue),
            'new_value' => $redactor->redact($entry->newValue),
            'meta' => $redactor->redact($entry->meta),
        ];
    }

    private function ipAddress(): string
    {
        try {
            return (string) (request()->getServerParams()['REMOTE_ADDR'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function repository(): AuditLogRepository
    {
        return $this->repository ?? new AuditLogRepository();
    }

    private function redactor(): AuditRedactor
    {
        return $this->redactor ?? new AuditRedactor();
    }

    private function actorResolver(): AuditActorResolver
    {
        return $this->actorResolver ?? new AuditActorResolver();
    }
}
