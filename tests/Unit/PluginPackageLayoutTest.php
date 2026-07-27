<?php

declare(strict_types=1);

namespace Tests\Unit;

use src\Mcp\Tools\SystemHealthTool;
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
    }

    public function testPluginConventionsAndBootstrapAreHostSafe(): void
    {
        foreach (['src/config.php', 'src/routes.php', 'src/functions.php', 'src/views', 'bootstrap.php'] as $path) {
            self::assertFileExists(BASE_PATH . '/' . $path);
        }

        $bootstrap = (string) file_get_contents(BASE_PATH . '/bootstrap.php');
        self::assertStringNotContainsString('vendor/autoload.php', $bootstrap);
        self::assertStringNotContainsString('BASE_PATH', $bootstrap);
        self::assertStringNotContainsString('app()->run()', $bootstrap);
        self::assertStringContainsString('MigrationRegistry::addPath', $bootstrap);
        self::assertStringContainsString('cms()->blockTypes()->add', $bootstrap);
        self::assertStringContainsString('tool()->register', $bootstrap);
    }

    public function testFrameworkDiscoversCorePluginResources(): void
    {
        $plugin = app()->getPlugin('fkde/nixcms');

        self::assertContains(realpath(BASE_PATH . '/src/views'), array_map('realpath', $plugin->getViewPaths()));
        self::assertContains(realpath(BASE_PATH . '/app/Migrations'), array_map('realpath', MigrationRegistry::getPaths()));
        self::assertNotNull(app()->container()->get(CommandRegistry::class)->get('user:create'));

        $tools = app()->container()->get(ToolRegistry::class);
        self::assertInstanceOf(SystemHealthTool::class, $tools->getTool('cms_system_health'));
    }
}
