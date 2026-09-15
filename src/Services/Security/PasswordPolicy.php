<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\CMS\Contracts\PasswordPolicyInterface;

final class PasswordPolicy implements PasswordPolicyInterface
{
    public const int MINIMUM_LENGTH = 8;
    public const int MAXIMUM_LENGTH = 4096;

    public function accepts(string $password): bool
    {
        $length = mb_strlen($password);

        return $length >= $this->minimumLength() && $length <= $this->maximumLength();
    }

    public function message(): string
    {
        return sprintf('Please enter a password with at least %d characters.', $this->minimumLength());
    }

    public function minimumLength(): int
    {
        return self::MINIMUM_LENGTH;
    }

    public function maximumLength(): int
    {
        return self::MAXIMUM_LENGTH;
    }
}
