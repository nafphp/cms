<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseDocumentationTest extends TestCase
{
    public function testComposerArchiveExcludesInstallationOwnedThemeAssets(): void
    {
        $composer = json_decode(
            (string) file_get_contents(BASE_PATH . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $excluded = $composer['archive']['exclude'] ?? [];

        self::assertContains('/public/css/themes', $excluded);
        self::assertContains('/public/js/themes', $excluded);
    }

    public function testReleaseDocumentsAndVersionsAreConsistent(): void
    {
        foreach (['LICENSE', 'README.md', 'SECURITY.md', 'CHANGELOG.md', 'RELEASE_NOTES_0.0.1.md', 'RELEASE_CHECKLIST.md'] as $file) {
            self::assertFileExists(BASE_PATH . '/' . $file);
        }

        $composer = json_decode((string) file_get_contents(BASE_PATH . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $package = json_decode((string) file_get_contents(BASE_PATH . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('version', $composer);
        self::assertSame('0.0.1', $package['version']);
        self::assertTrue($package['private']);
        self::assertSame('MIT', $composer['license']);
        self::assertSame('https://github.com/nixphp/cms', $composer['homepage']);
    }

    public function testPublicDocsDescribeSafeInstallationAndPrivateReporting(): void
    {
        $readme = (string) file_get_contents(BASE_PATH . '/README.md');
        foreach (['composer require nixphp/cms', 'nixcms:assets:publish', 'db:migrate up', 'user:create', 'public', 'Sitebuilder Preview', 'AI and MCP features are permission-, scope-, and tool-driven'] as $needle) {
            self::assertStringContainsString($needle, $readme);
        }
        self::assertStringNotContainsString('change-me', $readme);
        self::assertStringNotContainsString('CMS_SETUP_MODE', $readme);

        $security = (string) file_get_contents(BASE_PATH . '/SECURITY.md');
        self::assertStringContainsString('Do not open a public issue', $security);
        self::assertStringContainsString('/security/advisories/new', $security);
        self::assertStringContainsString('office@florianknapp.de', $security);
    }
}
