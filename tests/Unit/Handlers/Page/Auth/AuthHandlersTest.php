<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Page\Auth;

use Naf\CMS\Contracts\RateLimiterInterface;
use Naf\CMS\Handlers\Page\Auth\LoginUser;
use Naf\CMS\Handlers\Page\Auth\RegisterInvitedUser;
use Naf\CMS\Services\Security\RateLimitDecision;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class AuthHandlersTest extends DatabaseTestCase
{
    public function testLoginRejectsMissingCredentialsBeforeRateLimiting(): void
    {
        JsonRequest::bind([]);
        $limiter = new AuthRateLimiterSpy();

        $response = (new LoginUser($limiter))();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Please enter username and password.', (string) $response->getBody());
        self::assertSame(0, $limiter->inspections);
    }

    public function testRegistrationRejectsUnknownInviteBeforeReadingFormData(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE user_invites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_hash TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL,
                roles_json TEXT NOT NULL,
                created_by_user_id INTEGER NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
        JsonRequest::bind([]);

        $response = (new RegisterInvitedUser())('unknown-token');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Invite link is invalid.', (string) $response->getBody());
    }
}

final class AuthRateLimiterSpy implements RateLimiterInterface
{
    public int $inspections = 0;

    public function inspect(string $username, string $ipAddress): RateLimitDecision
    {
        $this->inspections++;
        return new RateLimitDecision(true, 5, 0);
    }

    public function recordFailure(string $username, string $ipAddress): RateLimitDecision
    {
        return new RateLimitDecision(true, 4, 0);
    }

    public function clearSuccessfulPair(string $username, string $ipAddress): void
    {
    }
}
