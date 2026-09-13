<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\User;

use Naf\CMS\Handlers\Api\User\StoreUser;
use Naf\CMS\Handlers\Api\User\UserPayloadValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\JsonRequest;

final class UserHandlersValidationTest extends TestCase
{
    public function testValidatorRejectsUnsafeCreatePayload(): void
    {
        $errors = (new UserPayloadValidator())->validate([
            'username' => 'x',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'different',
            'roles' => [],
        ], true);

        foreach (['username', 'email', 'password', 'password_confirmation', 'roles'] as $field) {
            self::assertArrayHasKey($field, $errors);
        }
    }

    public function testStoreUserStopsBeforePersistenceWhenValidationFails(): void
    {
        JsonRequest::bind(['username' => 'x']);
        $response = (new StoreUser())();
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertArrayHasKey('username', $payload['errors']);
    }
}
