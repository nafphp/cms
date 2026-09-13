<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Http;

use Naf\CMS\Exceptions\OutboundHttpDenied;
use Naf\CMS\Services\Http\OutboundHttpClient;
use Naf\CMS\Services\Http\OutboundHttpPolicy;
use Naf\CMS\Services\Http\OutboundHttpProfile;
use Naf\CMS\Services\Http\OutboundHttpTarget;
use PHPUnit\Framework\TestCase;

final class OutboundHttpClientTest extends TestCase
{
    public function testRedirectTargetIsResolvedAndAuthorizedAgain(): void
    {
        $calls = [];
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
        $client = new OutboundHttpClient($policy, static function (OutboundHttpTarget $target) use (&$calls): array {
            $calls[] = $target->url;
            return count($calls) === 1
                ? ['status' => 302, 'headers' => ['location' => 'https://second.example/final'], 'body' => '']
                : ['status' => 204, 'headers' => [], 'body' => ''];
        });

        $response = $client->request('https://first.example/hook', 'POST', '{}', OutboundHttpProfile::webhook());

        self::assertSame(204, $response->status);
        self::assertSame(['https://first.example/hook', 'https://second.example/final'], $calls);
    }

    public function testRedirectToBlockedNetworkIsRejectedBeforeSecondRequest(): void
    {
        $calls = 0;
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
        $client = new OutboundHttpClient($policy, static function () use (&$calls): array {
            $calls++;
            return ['status' => 302, 'headers' => ['location' => 'https://127.0.0.1/internal'], 'body' => ''];
        });

        try {
            $client->request('https://public.example/hook', 'POST', '{}', OutboundHttpProfile::webhook());
            self::fail('Expected blocked redirect to be rejected.');
        } catch (OutboundHttpDenied) {
            self::assertSame(1, $calls);
        }
    }

    public function testProtocolRelativeRedirectCannotBypassTargetPolicy(): void
    {
        $calls = 0;
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
        $client = new OutboundHttpClient($policy, static function () use (&$calls): array {
            $calls++;
            return ['status' => 302, 'headers' => ['location' => '//127.0.0.1/internal'], 'body' => ''];
        });

        try {
            $client->request('https://public.example/hook', 'POST', '{}', OutboundHttpProfile::webhook());
            self::fail('Expected protocol-relative redirect to be rejected.');
        } catch (OutboundHttpDenied) {
            self::assertSame(1, $calls);
        }
    }

    public function testDnsChangeOnRedirectIsRechecked(): void
    {
        $resolutions = 0;
        $calls = 0;
        $policy = new OutboundHttpPolicy(static function (string $host) use (&$resolutions): array {
            $resolutions++;
            return $resolutions === 1 ? ['93.184.216.34'] : ['169.254.169.254'];
        });
        $client = new OutboundHttpClient($policy, static function () use (&$calls): array {
            $calls++;
            return ['status' => 307, 'headers' => ['location' => 'https://rebind.example/next'], 'body' => ''];
        });

        try {
            $client->request('https://rebind.example/start', 'POST', '{}', OutboundHttpProfile::webhook());
            self::fail('Expected DNS change to be rejected.');
        } catch (OutboundHttpDenied) {
            self::assertSame(1, $calls);
            self::assertSame(2, $resolutions);
        }
    }

    public function testInjectedTransportCannotBypassResponseSizeLimit(): void
    {
        $policy = new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
        $client = new OutboundHttpClient(
            $policy,
            static fn(): array => ['status' => 200, 'headers' => [], 'body' => '12345'],
            maxResponseBytes: 4,
        );

        $this->expectException(\RuntimeException::class);
        $client->request('https://public.example/hook', 'GET', '', OutboundHttpProfile::webhook());
    }
}
