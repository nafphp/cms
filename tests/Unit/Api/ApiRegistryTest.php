<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Api\ApiRegistry;
use PHPUnit\Framework\TestCase;

final class ApiRegistryTest extends TestCase
{
    public function testRegistryFindsEndpointByIdAndPath(): void
    {
        $endpoint = ApiEndpoint::get('api.admin.summary', '/api/admin/summary')
            ->setHandler(static fn() => null);

        $registry = new ApiRegistry();
        $registry->add($endpoint);

        self::assertSame($endpoint, $registry->get('api.admin.summary'));
        self::assertSame($endpoint, $registry->findByPath('/api/admin/summary', 'GET'));
    }

    public function testRegistryRequiresExplicitReplacement(): void
    {
        $registry = new ApiRegistry();
        $registry->add(ApiEndpoint::get('api.admin.summary', '/api/admin/summary')->setHandler(static fn() => null));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('API endpoint "api.admin.summary" is already registered.');

        $registry->add(ApiEndpoint::get('api.admin.summary', '/api/admin/other')->setHandler(static fn() => null));
    }

    public function testRegistryCanReplaceEndpoint(): void
    {
        $registry = new ApiRegistry();
        $registry->add(ApiEndpoint::get('api.admin.summary', '/api/admin/summary')->setHandler(static fn() => null));

        $replacement = ApiEndpoint::get('api.admin.summary', '/api/admin/other')->setHandler(static fn() => null);
        $registry->add($replacement, replace: true);

        self::assertSame($replacement, $registry->get('api.admin.summary'));
        self::assertSame($replacement, $registry->findByPath('/api/admin/other', 'GET'));
    }
}
