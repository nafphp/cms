<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use Naf\CMS\Handlers\Api\User\UserPayloadValidator;
use Naf\CMS\Services\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testSharedPasswordLengthBoundaries(): void
    {
        $policy = new PasswordPolicy();

        self::assertFalse($policy->accepts(str_repeat('a', $policy->minimumLength() - 1)));
        self::assertTrue($policy->accepts(str_repeat('a', $policy->minimumLength())));
        self::assertTrue($policy->accepts(str_repeat('a', $policy->maximumLength())));
        self::assertFalse($policy->accepts(str_repeat('a', $policy->maximumLength() + 1)));
    }

    public function testAdminUserValidationUsesSharedPolicy(): void
    {
        $errors = (new UserPayloadValidator())->validate([
            'username' => 'admin',
            'password' => 'short',
            'password_confirmation' => 'short',
            'roles' => ['admin'],
        ], true);

        self::assertSame([(new PasswordPolicy())->message()], $errors['password']);
    }
}
