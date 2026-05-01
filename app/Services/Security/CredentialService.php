<?php

declare(strict_types=1);

namespace NixCMS\Services\Security;

class CredentialService
{
    private const int PASSWORD_MEMORY_COST = 19456;
    private const int PASSWORD_TIME_COST = 2;
    private const int PASSWORD_THREADS = 1;

    public function hashPassword(string $password): string
    {
        $hash = \password_hash($password, PASSWORD_ARGON2ID, $this->passwordOptions());
        if ($hash === false) {
            throw new \RuntimeException('Could not hash password.');
        }

        return $hash;
    }

    public function verifyPassword(string $submitted, ?string $stored): PasswordVerificationResult
    {
        if ($stored === null || $stored === '') {
            return new PasswordVerificationResult(false, false);
        }

        if (\password_verify($submitted, $stored)) {
            $needsUpgrade = \password_needs_rehash($stored, PASSWORD_ARGON2ID, $this->passwordOptions());
            return new PasswordVerificationResult(true, $needsUpgrade);
        }

        return new PasswordVerificationResult(false, false);
    }

    /**
     * @return array{memory_cost:int,time_cost:int,threads:int}
     */
    private function passwordOptions(): array
    {
        return [
            'memory_cost' => self::PASSWORD_MEMORY_COST,
            'time_cost' => self::PASSWORD_TIME_COST,
            'threads' => self::PASSWORD_THREADS,
        ];
    }
}
