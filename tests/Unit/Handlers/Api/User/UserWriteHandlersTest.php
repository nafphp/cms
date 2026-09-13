<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\User;

use Naf\CMS\Handlers\Api\User\CreateInvite;
use Naf\CMS\Handlers\Api\User\DeleteUser;
use Naf\CMS\Handlers\Api\User\UpdateUser;
use Naf\CMS\Handlers\Api\User\UpdateUserPassword;
use Naf\CMS\Handlers\Api\User\UpdateUserRoles;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class UserWriteHandlersTest extends DatabaseTestCase
{
    /**
     * @param class-string $handler
     */
    #[DataProvider('invalidWriteProvider')]
    public function testWriteHandlersRejectIncompletePayloads(string $handler): void
    {
        JsonRequest::bind([]);
        $response = (new $handler())();

        self::assertSame(400, $response->getStatusCode(), $handler);
        self::assertFalse($this->json($response)['success'], $handler);
    }

    /** @return iterable<string, array{class-string}> */
    public static function invalidWriteProvider(): iterable
    {
        yield 'create invite' => [CreateInvite::class];
        yield 'delete user' => [DeleteUser::class];
        yield 'update user' => [UpdateUser::class];
        yield 'update password' => [UpdateUserPassword::class];
        yield 'update roles' => [UpdateUserRoles::class];
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
