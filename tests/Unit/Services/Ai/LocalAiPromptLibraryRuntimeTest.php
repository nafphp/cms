<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use Naf\CMS\Services\Ai\LocalAiPromptLibrary;
use PHPUnit\Framework\TestCase;

final class LocalAiPromptLibraryRuntimeTest extends TestCase
{
    public function testHostOverrideWinsWithoutChangingPackageDefault(): void
    {
        $root = sys_get_temp_dir() . '/nafcms-prompts-' . bin2hex(random_bytes(6));
        $defaults = $root . '/defaults';
        $overrides = $root . '/runtime';
        mkdir($defaults, 0777, true);
        file_put_contents($defaults . '/IDENTITY.md', "Package identity\n");
        file_put_contents($defaults . '/MEMORY.md', "No durable assistant memory has been recorded yet.\n");
        file_put_contents($defaults . '/MISTAKES.md', "# Mistakes\n");

        $library = new LocalAiPromptLibrary($overrides, $defaults);
        self::assertSame("Package identity\n", $library->promptFile('IDENTITY.md'));

        $library->savePromptFile('IDENTITY.md', 'Host identity');
        $memory = $library->appendMemory('Prefer registry metadata.');

        self::assertSame("Host identity\n", $library->promptFile('IDENTITY.md'));
        self::assertSame("Package identity\n", file_get_contents($defaults . '/IDENTITY.md'));
        self::assertSame('- Prefer registry metadata.', $memory);
        self::assertFileExists($overrides . '/MEMORY.md');
        self::assertSame(0640, fileperms($overrides . '/MEMORY.md') & 0777);
    }
}
