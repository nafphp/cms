<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\CMS\Contracts\AuditSinkInterface;
use Naf\CMS\Contracts\MediaStorageInterface;
use Naf\CMS\Contracts\OutboundHttpClientInterface;
use Naf\CMS\Contracts\PasswordPolicyInterface;
use Naf\CMS\Contracts\RateLimiterInterface;
use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Contracts\ThemeSourceInterface;
use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Contracts\UserPrivilegePolicyInterface;
use Naf\CMS\Services\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

use function Naf\app;

/**
 * The overridable service surface.
 *
 * Every interface listed here is part of the promise that a host can replace CMS
 * behaviour without forking the plugin. Two things have to hold for that promise:
 * the default is registered, and a host binding actually wins over it.
 */
final class ServiceBindingTest extends TestCase
{
    /** @var list<class-string> */
    private const array OVERRIDABLE = [
        AuditSinkInterface::class,
        MediaStorageInterface::class,
        OutboundHttpClientInterface::class,
        PasswordPolicyInterface::class,
        RateLimiterInterface::class,
        SettingsServiceInterface::class,
        ThemeSourceInterface::class,
        TranslationServiceInterface::class,
        UserPrivilegePolicyInterface::class,
    ];

    public function testEveryOverridableServiceHasADefaultBinding(): void
    {
        $missing = [];

        foreach (self::OVERRIDABLE as $interface) {
            if (!app()->container()->has($interface)) {
                $missing[] = $interface;
            }
        }

        self::assertSame([], $missing, sprintf(
            "These interfaces have no default binding, so resolving them fails:\n- %s",
            implode("\n- ", $missing),
        ));
    }

    public function testDefaultBindingsResolveToAnInstanceOfTheirInterface(): void
    {
        foreach (self::OVERRIDABLE as $interface) {
            self::assertInstanceOf($interface, app()->container()->get($interface), $interface);
        }
    }

    public function testHostBindingReplacesTheShippedDefault(): void
    {
        $container = app()->container();
        $original  = $container->get(PasswordPolicyInterface::class);

        try {
            $container->set(PasswordPolicyInterface::class, static fn(): PasswordPolicyInterface => new class implements PasswordPolicyInterface {
                public function accepts(string $password): bool
                {
                    return false;
                }

                public function message(): string
                {
                    return 'Rejected by the host policy.';
                }

                public function minimumLength(): int
                {
                    return 64;
                }

                public function maximumLength(): int
                {
                    return 65;
                }
            });

            $resolved = $container->get(PasswordPolicyInterface::class);

            self::assertNotInstanceOf(PasswordPolicy::class, $resolved);
            self::assertFalse($resolved->accepts(str_repeat('a', 32)));
            self::assertSame('Rejected by the host policy.', $resolved->message());
        } finally {
            $container->set(PasswordPolicyInterface::class, static fn() => $original);
        }
    }
}
