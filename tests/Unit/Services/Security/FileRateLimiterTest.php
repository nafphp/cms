<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use NixPHP\CMS\Services\Security\FileRateLimiter;
use NixPHP\CMS\Services\Security\LoginRateLimiter;
use PHPUnit\Framework\TestCase;

final class FileRateLimiterTest extends TestCase
{
    public function testAttemptsAreLimitedAtomicallyAndResetAfterTtl(): void
    {
        $now = 1000;
        $directory = sys_get_temp_dir() . '/nixcms-rate-test-' . bin2hex(random_bytes(6));
        $limiter = new FileRateLimiter($directory, static function () use (&$now): int {
            return $now;
        });

        self::assertTrue($limiter->hit('login:test', 2, 60)->allowed);
        self::assertTrue($limiter->hit('login:test', 2, 60)->allowed);
        $blocked = $limiter->hit('login:test', 2, 60);
        self::assertFalse($blocked->allowed);
        self::assertSame(60, $blocked->retryAfter);

        $now += 61;
        self::assertTrue($limiter->inspect('login:test', 2, 60)->allowed);
        self::assertTrue($limiter->hit('login:test', 2, 60)->allowed);
    }

    public function testLoginLimiterUsesTemporaryPairBucketWithoutStoringIdentifiers(): void
    {
        $directory = sys_get_temp_dir() . '/nixcms-login-rate-test-' . bin2hex(random_bytes(6));
        $limiter = new LoginRateLimiter(new FileRateLimiter($directory, static fn(): int => 2000));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue($limiter->recordFailure('Ada@example.test', '203.0.113.20')->allowed);
        }
        self::assertFalse($limiter->recordFailure('Ada@example.test', '203.0.113.20')->allowed);
        self::assertTrue($limiter->inspect('different-user', '203.0.113.20')->allowed);

        $files = glob($directory . '/*/*.json') ?: [];
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            self::assertStringNotContainsString('Ada', $contents);
            self::assertStringNotContainsString('203.0.113.20', $contents);
        }

        $limiter->clearSuccessfulPair('Ada@example.test', '203.0.113.20');
        self::assertTrue($limiter->inspect('Ada@example.test', '203.0.113.20')->allowed);
    }
}
