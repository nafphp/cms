<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Security;

final class PasswordPolicy
{
    public const int MINIMUM_LENGTH = 8;
    public const int MAXIMUM_LENGTH = 4096;

    public static function accepts(string $password): bool
    {
        $length = mb_strlen($password);
        return $length >= self::MINIMUM_LENGTH && $length <= self::MAXIMUM_LENGTH;
    }

    public static function message(): string
    {
        return sprintf('Please enter a password with at least %d characters.', self::MINIMUM_LENGTH);
    }
}
