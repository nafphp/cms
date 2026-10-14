<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use RuntimeException;

class CredentialService
{
    private const string DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=19456,t=2,p=1$NVJBQllNNGZHM2R1aGh3NA$ZU5byYnM1xYejc1P0bUASjmEqCZsX4nySM1qPBaukYg';
    private const int PASSWORD_MEMORY_COST   = 19456;
    private const int PASSWORD_TIME_COST     = 2;
    private const int PASSWORD_THREADS       = 1;

    public function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, $this->passwordOptions());
        if ($hash === false) {
            throw new RuntimeException('Could not hash password.');
        }

        return $hash;
    }

    public function verifyPassword(string $submitted, ?string $stored): PasswordVerificationResult
    {
        if ($stored === null || $stored === '') {
            password_verify($submitted, self::DUMMY_PASSWORD_HASH);

            return new PasswordVerificationResult(false, false);
        }

        if (password_verify($submitted, $stored)) {
            $needsUpgrade = password_needs_rehash($stored, PASSWORD_ARGON2ID, $this->passwordOptions());

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
            'time_cost'   => self::PASSWORD_TIME_COST,
            'threads'     => self::PASSWORD_THREADS,
        ];
    }
}
