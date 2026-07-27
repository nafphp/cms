<?php

declare(strict_types=1);

namespace src\Services\Security;

class PasswordVerificationResult
{
    public function __construct(
        private readonly bool $verified,
        private readonly bool $requiresUpgrade,
    ) {
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function requiresUpgrade(): bool
    {
        return $this->requiresUpgrade;
    }
}
