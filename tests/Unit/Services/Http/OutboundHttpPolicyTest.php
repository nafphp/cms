<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Http;

use Naf\CMS\Exceptions\OutboundHttpDenied;
use Naf\CMS\Services\Http\OutboundHttpPolicy;
use Naf\CMS\Services\Http\OutboundHttpProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboundHttpPolicyTest extends TestCase
{
    #[DataProvider('blockedPublicTargets')]
    public function testPublicWebhookBlocksNonPublicIPv4AndIpv6(string $url): void
    {
        $this->expectException(OutboundHttpDenied::class);
        (new OutboundHttpPolicy())->authorize($url, OutboundHttpProfile::webhook());
    }

    public static function blockedPublicTargets(): iterable
    {
        yield 'IPv4 loopback' => ['https://127.0.0.1/hook'];
        yield 'RFC1918 10/8' => ['https://10.1.2.3/hook'];
        yield 'RFC1918 172.16/12' => ['https://172.16.0.1/hook'];
        yield 'RFC1918 192.168/16' => ['https://192.168.1.10/hook'];
        yield 'link-local metadata' => ['https://169.254.169.254/latest/meta-data'];
        yield 'IPv4 multicast' => ['https://224.0.0.1/hook'];
        yield 'IPv4 documentation' => ['https://192.0.2.1/hook'];
        yield 'IPv6 loopback' => ['https://[::1]/hook'];
        yield 'IPv6 unique-local' => ['https://[fd00::1]/hook'];
        yield 'IPv6 link-local' => ['https://[fe80::1]/hook'];
        yield 'IPv6 multicast' => ['https://[ff02::1]/hook'];
        yield 'IPv6 documentation' => ['https://[2001:db8::1]/hook'];
    }

    public function testPublicWebhookRequiresHttpsAndRejectsCredentials(): void
    {
        foreach (['http://93.184.216.34/hook', 'https://user:secret@93.184.216.34/hook'] as $url) {
            try {
                (new OutboundHttpPolicy())->authorize($url, OutboundHttpProfile::webhook());
                self::fail('Expected webhook target to be denied.');
            } catch (OutboundHttpDenied) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEveryResolvedAddressMustBePublic(): void
    {
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34', '127.0.0.1']);

        $this->expectException(OutboundHttpDenied::class);
        $policy->authorize('https://mixed.example/hook', OutboundHttpProfile::webhook());
    }

    public function testPublicHttpsTargetIsAllowedAndResolutionIsReturnedForPinning(): void
    {
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
        $target = $policy->authorize('https://public.example/hook', OutboundHttpProfile::webhook());

        self::assertSame('public.example', $target->host);
        self::assertSame(443, $target->port);
        self::assertSame(['93.184.216.34'], $target->addresses);
    }

    public function testExplicitDevelopmentWebhookExceptionIsHostBound(): void
    {
        $policy  = new OutboundHttpPolicy(static fn(string $host): array => ['127.0.0.1']);
        $profile = OutboundHttpProfile::webhook(true, ['dev-webhook.local']);

        self::assertSame(
            ['127.0.0.1'],
            $policy->authorize('http://dev-webhook.local:8080/hook', $profile)->addresses,
        );

        $this->expectException(OutboundHttpDenied::class);
        $policy->authorize('http://other.local:8080/hook', $profile);
    }

    public function testOllamaAllowsOnlyDocumentedLoopbackHostsAndPorts(): void
    {
        $policy  = new OutboundHttpPolicy(static fn(string $host): array => $host === 'localhost' ? ['127.0.0.1', '::1'] : ['192.168.1.20']);
        $profile = OutboundHttpProfile::ollama([11434, 11435]);

        self::assertSame(11434, $policy->authorize('http://localhost:11434/api/embed', $profile)->port);

        foreach (['http://localhost:8080/api/embed', 'http://ollama.lan:11434/api/embed', 'http://192.168.1.20:11434/api/embed'] as $url) {
            try {
                $policy->authorize($url, $profile);
                self::fail('Expected Ollama target to be denied.');
            } catch (OutboundHttpDenied) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testOllamaAllowsBracketedIpv6LoopbackLiteral(): void
    {
        $target = (new OutboundHttpPolicy())->authorize(
            'http://[::1]:11434/api/embed',
            OutboundHttpProfile::ollama([11434]),
        );

        self::assertSame(['::1'], $target->addresses);
        self::assertSame(11434, $target->port);
    }
}
