<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Repositories\AuditLogRepository;
use function NixPHP\request;

final class Audit
{
    public function __construct(private readonly ?AuditLogRepository $repository = null)
    {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function record(
        string $action,
        string $subjectType,
        string $subjectKey,
        mixed $oldValue = null,
        mixed $newValue = null,
        array $meta = [],
    ): bool {
        $actor = \user()?->get();

        return $this->repository()->record([
            'actor_user_id' => $actor?->getId(),
            'actor_label' => $actor?->getDisplayName() ?? (\user()?->isLoggedIn() === true ? 'System admin' : 'System'),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'meta' => $meta,
            'ip_address' => $this->ipAddress(),
        ]);
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
}
