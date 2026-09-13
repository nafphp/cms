<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Api\Settings\GetTranslations;
use Naf\CMS\Handlers\Api\Settings\UpdateTranslations;
use PHPUnit\Framework\TestCase;

final class TranslationsApiTest extends TestCase
{
    public function testTranslationReadEndpointIsAvailableToAdminAndPageEditors(): void
    {
        $endpoints = require dirname(__DIR__, 3) . '/src/Resources/api/settings.php';
        $byId = [];
        foreach ($endpoints as $endpoint) {
            $byId[$endpoint->id()] = $endpoint;
        }

        self::assertSame(GetTranslations::class, $byId['api.admin.translations.show']->handler());
        self::assertSame('GET', $byId['api.admin.translations.show']->method());
        self::assertSame('/api/admin/translations/{language}', $byId['api.admin.translations.show']->path());
        self::assertTrue($byId['api.admin.translations.show']->requiresAuthentication());
        self::assertTrue($byId['api.admin.translations.show']->requiresAnyPermission());
        self::assertSame([
            Permission::ADMIN_ACCESS,
            Permission::PAGES_VIEW,
            Permission::PAGES_CREATE,
            Permission::PAGES_EDIT,
        ], $byId['api.admin.translations.show']->requiredPermissions());

        self::assertSame(UpdateTranslations::class, $byId['api.admin.translations.update']->handler());
        self::assertSame('POST', $byId['api.admin.translations.update']->method());
        self::assertSame([Permission::ADMIN_ACCESS], $byId['api.admin.translations.update']->requiredPermissions());
    }
}
