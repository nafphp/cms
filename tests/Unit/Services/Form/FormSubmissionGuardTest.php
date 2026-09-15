<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Form;

use Naf\CMS\Events\FormPayloadLimitEvent;
use Naf\CMS\Services\Form\FormSubmissionGuard;
use Naf\CMS\Services\Security\FileRateLimiter;
use Naf\Exceptions\AbortException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class FormSubmissionGuardTest extends TestCase
{
    public function testFormRateLimitIsScopedAndTemporary(): void
    {
        $now     = 1000;
        $limiter = new FileRateLimiter(
            sys_get_temp_dir() . '/nafcms-form-rate-test-' . bin2hex(random_bytes(6)),
            static function () use (&$now): int {
                return $now;
            },
        );
        $guard = new FormSubmissionGuard($limiter);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            self::assertTrue($guard->consume('contact', '198.51.100.10')->allowed);
        }
        self::assertFalse($guard->consume('contact', '198.51.100.10')->allowed);
        self::assertTrue($guard->consume('other-form', '198.51.100.10')->allowed);

        $now += 61;
        self::assertTrue($guard->consume('contact', '198.51.100.10')->allowed);
    }

    public function testPayloadAndFieldSizeLimitsAreServerSide(): void
    {
        $guard = new FormSubmissionGuard(new FileRateLimiter(sys_get_temp_dir() . '/nafcms-form-payload-' . bin2hex(random_bytes(6))));

        $errors = $guard->validatePayload(
            str_repeat('x', FormSubmissionGuard::MAXIMUM_BODY_BYTES + 1),
            ['message' => str_repeat('x', FormSubmissionGuard::MAXIMUM_FIELD_BYTES + 1), 'nested' => ['invalid']],
        );

        self::assertSame(['Form payload is too large.'], $errors['_payload']);
        self::assertSame(['Field value is too large.'], $errors['message']);
        self::assertSame(['Invalid field value.'], $errors['nested']);
    }

    public function testOversizedContentLengthIsRejectedBeforeFormController(): void
    {
        $request = (new ServerRequest('POST', '/api/forms/contact/submit'))
            ->withHeader('Content-Length', (string) (FormSubmissionGuard::MAXIMUM_BODY_BYTES + 1));

        $this->expectException(AbortException::class);
        (new FormPayloadLimitEvent())->handle($request);
    }

    public function testHoneypotAndMinimumFillTimeDetectSpam(): void
    {
        $guard = new FormSubmissionGuard(new FileRateLimiter(sys_get_temp_dir() . '/nafcms-form-spam-' . bin2hex(random_bytes(6))));

        self::assertTrue($guard->looksLikeSpam(['_nafcms_hp' => 'https://spam.test', '_nafcms_started_at' => 90], 100));
        self::assertTrue($guard->looksLikeSpam(['_nafcms_hp' => '', '_nafcms_started_at' => 99], 100));
        self::assertFalse($guard->looksLikeSpam(['_nafcms_hp' => '', '_nafcms_started_at' => 90], 100));
    }
}
