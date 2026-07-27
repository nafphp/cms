<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use NixPHP\CMS\Localization\LanguageRegistry;
use PHPUnit\Framework\TestCase;

final class LanguageRegistryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/nixcms-lang-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    public function testAvailableDiscoversReadableLanguageFiles(): void
    {
        file_put_contents($this->directory . '/nl.json', '{}');
        file_put_contents($this->directory . '/pt-br.json', '{"admin":"Admin"}');

        $languages = (new LanguageRegistry($this->directory))->available();

        self::assertContains('nl', $languages);
        self::assertContains('pt-br', $languages);
    }

    public function testEmptyLanguageDirectoryFallsBackToDefaults(): void
    {
        $languages = (new LanguageRegistry($this->directory))->available();

        self::assertSame(['de', 'en', 'es', 'fr', 'it'], $languages);
    }

    public function testKnownLanguagesIncludeNixPhpLanguageCodes(): void
    {
        $languages = (new LanguageRegistry($this->directory))->known();

        self::assertContains('nl', $languages);
        self::assertContains('pt', $languages);
    }

    public function testKnownOptionsUseNixPhpLanguageLabels(): void
    {
        $options = (new LanguageRegistry($this->directory))->knownOptions();

        self::assertSame('Nederlands', $options['nl']);
    }

    public function testMalformedJsonLanguageFilesAreIgnoredDuringDiscovery(): void
    {
        file_put_contents($this->directory . '/nl.json', '{');

        $languages = (new LanguageRegistry($this->directory))->available();

        self::assertNotContains('nl', $languages);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($directory);
    }
}
