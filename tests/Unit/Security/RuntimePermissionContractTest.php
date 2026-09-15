<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Enums\Permission;
use PHPUnit\Framework\TestCase;

final class RuntimePermissionContractTest extends TestCase
{
    public function testAiInstructionEndpointsUseDedicatedPermission(): void
    {
        $endpoints = require BASE_PATH . '/src/Resources/api/ai.php';

        foreach ($endpoints as $endpoint) {
            if (str_contains($endpoint->id(), '.tools')) {
                continue;
            }

            self::assertSame([Permission::AI_INSTRUCTIONS_MANAGE], $endpoint->requiredPermissions(), $endpoint->id());
        }
    }

    public function testThemeCodeEndpointUsesDedicatedPermission(): void
    {
        $endpoints = require BASE_PATH . '/src/Resources/api/themes.php';

        self::assertSame(
            [Permission::THEMES_CODE_MANAGE],
            $endpoints[0]->requiredPermissions(),
        );
    }

    public function testLocalPreviewHasNoPersistedApiKeySetting(): void
    {
        $config   = require BASE_PATH . '/src/config.php';
        $sections = require BASE_PATH . '/src/settings.php';
        $keys     = [];
        foreach ($sections as $section) {
            $keys = [...$keys, ...array_keys($section->fields())];
        }

        self::assertArrayNotHasKey('api_key', $config['ai']);
        self::assertNotContains('ai.api_key', $keys);
        self::assertStringNotContainsString(
            'api-key',
            (string) file_get_contents(BASE_PATH . '/public/js/admin/local-ai-settings.js'),
        );
    }
}
