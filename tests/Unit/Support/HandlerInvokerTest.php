<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use NixCMS\Support\HandlerInvoker;
use PHPUnit\Framework\TestCase;

final class HandlerInvokerTest extends TestCase
{
    public function testInvokesClosureHandler(): void
    {
        $result = HandlerInvoker::call(static fn(string $value): string => strtoupper($value), ['cms']);

        self::assertSame('CMS', $result);
    }

    public function testInvokesClassStringHandler(): void
    {
        self::assertSame('hello cms', HandlerInvoker::call(InvokableHandlerFixture::class, ['cms']));
    }

    public function testInvokesClassMethodHandler(): void
    {
        self::assertSame('method cms', HandlerInvoker::call([MethodHandlerFixture::class, 'handle'], ['cms']));
    }
}

final class InvokableHandlerFixture
{
    public function __invoke(string $value): string
    {
        return 'hello ' . $value;
    }
}

final class MethodHandlerFixture
{
    public function handle(string $value): string
    {
        return 'method ' . $value;
    }
}
