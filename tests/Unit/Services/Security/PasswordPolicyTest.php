<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use NixPHP\CMS\Handlers\Api\User\UserPayloadValidator;
use NixPHP\CMS\Services\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testSharedPasswordLengthBoundaries(): void
    {
        self::assertFalse(PasswordPolicy::accepts(str_repeat('a', PasswordPolicy::MINIMUM_LENGTH - 1)));
        self::assertTrue(PasswordPolicy::accepts(str_repeat('a', PasswordPolicy::MINIMUM_LENGTH)));
        self::assertTrue(PasswordPolicy::accepts(str_repeat('a', PasswordPolicy::MAXIMUM_LENGTH)));
        self::assertFalse(PasswordPolicy::accepts(str_repeat('a', PasswordPolicy::MAXIMUM_LENGTH + 1)));
    }

    public function testAdminUserValidationUsesSharedPolicy(): void
    {
        $errors = UserPayloadValidator::validate([
            'username' => 'admin',
            'password' => 'short',
            'password_confirmation' => 'short',
            'roles' => ['admin'],
        ], true);

        self::assertSame([PasswordPolicy::message()], $errors['password']);
    }
}
