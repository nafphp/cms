<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Support;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

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
