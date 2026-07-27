<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Developer;

use NixPHP\CMS\Services\Developer\AgentGuideInstaller;
use PHPUnit\Framework\TestCase;

final class AgentGuideInstallerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/nixcms-agent-guide-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testCreatesAndUpdatesOnlyTheManagedGuide(): void
    {
        $source = $this->directory . '/template.md';
        $destination = $this->directory . '/AGENTS.md';
        file_put_contents($source, AgentGuideInstaller::MARKER . "\n# Version one\n");
        $installer = new AgentGuideInstaller($source, $destination);

        self::assertSame('created', $installer->install()['status']);
        file_put_contents($source, AgentGuideInstaller::MARKER . "\n# Version two\n");
        self::assertSame('update_required', $installer->install()['status']);
        self::assertStringContainsString('Version one', (string) file_get_contents($destination));
        self::assertSame('updated', $installer->install(true)['status']);
        self::assertStringContainsString('Version two', (string) file_get_contents($destination));
    }

    public function testNeverOverwritesUserOwnedInstructions(): void
    {
        $source = $this->directory . '/template.md';
        $destination = $this->directory . '/AGENTS.md';
        file_put_contents($source, AgentGuideInstaller::MARKER . "\n# Managed\n");
        file_put_contents($destination, "# User instructions\n");

        $result = (new AgentGuideInstaller($source, $destination))->install(true);

        self::assertSame('unmanaged', $result['status']);
        self::assertSame("# User instructions\n", file_get_contents($destination));
    }
}
