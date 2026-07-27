<?php

declare(strict_types=1);

namespace Tests\Unit;

use src\Services\Developer\AgentGuideInstaller;
use NixPHP\CLI\Support\CommandRegistry;
use PHPUnit\Framework\TestCase;
use function NixPHP\app;

final class DeveloperExperienceIntegrityTest extends TestCase
{
    public function testShippedGuideIsPracticalPortableAndRegisteredForInstallation(): void
    {
        $guide = (string) file_get_contents(BASE_PATH . '/docs/templates/SHIPPED_AGENTS.md');

        self::assertStringContainsString(AgentGuideInstaller::MARKER, $guide);
        foreach (['app/pages.php', 'app/Resources/pages', 'app/api.php', 'app/Resources/api', 'app/content.php', 'app/Migrations', 'app/Mcp/Tools', 'views', 'vendor/bin/nix', 'permissions', 'scopes'] as $needle) {
            self::assertStringContainsStringIgnoringCase($needle, $guide);
        }
        self::assertStringNotContainsString('/Users/', $guide);
        self::assertStringNotContainsString('prompts/', $guide);
        self::assertStringNotContainsString('Task 0', $guide);
        self::assertNotNull(app()->container()->get(CommandRegistry::class)->get('nixcms:agents:install'));
    }

    public function testCompletePluginExampleKeepsAllExplicitExtensionPointsVisible(): void
    {
        $root = BASE_PATH . '/examples/05-complete-plugin';
        foreach (['README.md', 'bootstrap.php', 'plugin.php', 'src/Migrations/CreateReportsTable.php', 'src/views/example-plugin/report.phtml'] as $file) {
            self::assertFileExists($root . '/' . $file);
        }

        $registration = (string) file_get_contents($root . '/plugin.php');
        foreach (['cms()->pages()->add', 'cms()->api()->add', 'MigrationRegistry::addPath', 'tool()->register', "view('example-plugin.report')", 'LocalAiToolMetadataInterface'] as $needle) {
            self::assertStringContainsString($needle, $registration);
        }
    }
}
