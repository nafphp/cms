<?php

declare(strict_types=1);

namespace Tests\Unit;

use NixPHP\CMS\Mcp\Tools\SystemHealthTool;
use NixPHP\CLI\Support\CommandRegistry;
use NixPHP\Database\Support\MigrationRegistry;
use NixPHP\MCP\Support\ToolRegistry;
use PHPUnit\Framework\TestCase;
use function NixPHP\app;

final class PluginPackageLayoutTest extends TestCase
{
    public function testComposerMetadataDeclaresAReleaseReadyPlugin(): void
    {
        $composer = json_decode((string) file_get_contents(BASE_PATH . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('nixphp-plugin', $composer['type']);
        self::assertArrayNotHasKey('repositories', $composer);
        self::assertSame(['src/functions.php'], $composer['autoload']['files']);
        self::assertArrayNotHasKey('Tests\\', $composer['autoload']['psr-4']);
        self::assertSame('tests/', $composer['autoload-dev']['psr-4']['Tests\\']);
        self::assertStringNotContainsString('dev-', $composer['require']['nixphp/i18n']);
        self::assertArrayHasKey('nixphp/cli', $composer['require']);
        self::assertFileDoesNotExist(BASE_PATH . '/bin/nix');
    }

    public function testPluginConventionsAndBootstrapAreHostSafe(): void
    {
        foreach (['src/config.php', 'src/routes.php', 'src/functions.php', 'views', 'bootstrap.php'] as $path) {
            self::assertFileExists(BASE_PATH . '/' . $path);
        }

        $bootstrap = (string) file_get_contents(BASE_PATH . '/bootstrap.php');
        self::assertStringNotContainsString('vendor/autoload.php', $bootstrap);
        self::assertStringContainsString("if (!defined('BASE_PATH'))", $bootstrap);
        self::assertStringContainsString("define('BASE_PATH', __DIR__)", $bootstrap);
        self::assertStringNotContainsString('app()->run()', $bootstrap);
        self::assertStringNotContainsString('set(ToolRegistry::class', $bootstrap);
        self::assertFileDoesNotExist(BASE_PATH . '/src/blocks.php');
        self::assertStringContainsString('MigrationRegistry::addPath', $bootstrap);
        self::assertStringContainsString('cms()->blockTypes()->add', $bootstrap);
        self::assertStringContainsString('tool()->register', $bootstrap);
    }

    public function testFrameworkDiscoversCorePluginResources(): void
    {
        if (!class_exists(\NixPHP\Support\CoreFileLoader::class)) {
            self::markTestSkipped('Discovery of a top-level views/ directory needs nixphp/framework >= 0.1.6.');
        }

        $plugin = app()->getPlugin('nixphp/cms');

        self::assertContains(realpath(BASE_PATH . '/views'), array_map('realpath', $plugin->getViewPaths()));
        self::assertContains(realpath(BASE_PATH . '/src/Migrations'), array_map('realpath', MigrationRegistry::getPaths()));
        self::assertNotNull(app()->container()->get(CommandRegistry::class)->get('user:create'));

        $tools = app()->container()->get(ToolRegistry::class);
        self::assertInstanceOf(SystemHealthTool::class, $tools->getTool('cms_system_health'));
    }
}
