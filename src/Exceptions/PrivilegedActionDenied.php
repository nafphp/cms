<?php

declare(strict_types=1);

namespace src\Exceptions;

final class PrivilegedActionDenied extends \DomainException
{
    public function __construct(
        private readonly string $reason,
        string $message = 'This privileged action is not allowed.',
    ) {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
