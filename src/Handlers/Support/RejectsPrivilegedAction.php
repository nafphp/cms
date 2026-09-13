<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Support;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;

trait RejectsPrivilegedAction
{
    private function privilegedActionDenied(
        PrivilegedActionDenied $exception,
        string $operation,
        string $subjectType,
        string $subjectKey,
    ): ResponseInterface {
        AuditEntry::create(
            'security.privileged_action_denied',
            $subjectType,
            $subjectKey,
            null,
            null,
            ['operation' => $operation, 'reason' => $exception->reason()],
        )->record();

        return json(['success' => false, 'reason' => $exception->getMessage()], 403);
    }
}
