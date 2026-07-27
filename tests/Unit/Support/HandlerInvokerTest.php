<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use src\Support\HandlerInvoker;
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

    public function testInvokesObjectMethodHandlerWithNamedParameters(): void
    {
        $handler = new MethodHandlerFixture();

        self::assertSame('method cms', HandlerInvoker::call([$handler, 'handle'], ['value' => 'cms', 'ignored' => true]));
    }

    public function testDropsUnknownNamedParameters(): void
    {
        self::assertSame('handled', HandlerInvoker::call(NoArgumentHandlerFixture::class, ['navigationId' => '12']));
    }

    public function testPassesKnownNamedParameters(): void
    {
        $result = HandlerInvoker::call(
            static fn(string $navigationId): string => 'navigation ' . $navigationId,
            ['navigationId' => '12', 'ignored' => true]
        );

        self::assertSame('navigation 12', $result);
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

final class NoArgumentHandlerFixture
{
    public function __invoke(): string
    {
        return 'handled';
    }
}
