<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Repositories\AuditLogRepository;
use function NixPHP\request;

final class Audit
{
    public function __construct(
        private readonly ?AuditLogRepository $repository = null,
        private readonly ?AuditRedactor $redactor = null,
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

        $helper = function_exists('user') ? \user() : null;
        $actor = $helper?->get();

        return $this->repository()->record($this->redactEntry($entry) + [
            'actor_user_id' => $actor?->getId(),
            'actor_label' => $actor?->getDisplayName() ?? ($helper?->isLoggedIn() === true ? 'System admin' : 'System'),
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
}
