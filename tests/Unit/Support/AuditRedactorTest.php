<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use src\Support\AuditRedactor;
use PHPUnit\Framework\TestCase;

final class AuditRedactorTest extends TestCase
{
    public function testRedactsSensitiveArrayKeysRecursively(): void
    {
        $redacted = (new AuditRedactor())->redact([
            'email' => 'admin@example.test',
            'password' => 'secret',
            'nested' => [
                'inviteToken' => 'abc123',
                'title' => 'Visible',
            ],
        ]);

        self::assertSame('[redacted]', $redacted['password']);
        self::assertSame('[redacted]', $redacted['nested']['inviteToken']);
        self::assertSame('Visible', $redacted['nested']['title']);
    }

    public function testRedactsSensitiveParameterValue(): void
    {
        $redacted = (new AuditRedactor())->redact(new \SensitiveParameterValue('secret'));

        self::assertSame('[redacted]', $redacted);
    }

    public function testTreatsSensitiveConstructorParameterAsPropertySignal(): void
    {
        $payload = new AuditRedactorFixture('admin@example.test', 'invite-secret');
        $redacted = (new AuditRedactor())->redact($payload);

        self::assertSame('admin@example.test', $redacted['email']);
        self::assertSame('[redacted]', $redacted['token']);
    }
}

final class AuditRedactorFixture
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $token,
    ) {
    }
}
