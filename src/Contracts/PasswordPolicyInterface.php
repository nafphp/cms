<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

/**
 * Rules a password has to satisfy before it is accepted.
 *
 * The shipped implementation only enforces a length range. Hosts with their own
 * requirements — character classes, breach lists, an external identity provider —
 * bind their own implementation for this interface.
 * @api
 */
interface PasswordPolicyInterface
{
    public function accepts(string $password): bool;

    /** Human readable reason shown when accepts() returns false. */
    public function message(): string;

    public function minimumLength(): int;

    public function maximumLength(): int;
}
