<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

final class SecureConfigurationTest extends TestCase
{
    public function testPreviewDoesNotExposeAuthOffOrSetupModeSwitches(): void
    {
        $config = require BASE_PATH . '/src/config.php';
        $cms = is_array($config['cms'] ?? null) ? $config['cms'] : [];
        $environmentExample = (string) file_get_contents(BASE_PATH . '/.env.example');

        self::assertArrayNotHasKey('auth_enabled', $cms);
        self::assertArrayNotHasKey('setup_mode', $cms);
        self::assertStringNotContainsString('CMS_SETUP_MODE', $environmentExample);
    }
}
