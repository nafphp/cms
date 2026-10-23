<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use FilesystemIterator;
use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Commands\MakeBlockCommand;
use Naf\CMS\Commands\MakePluginCommand;
use Naf\CMS\Commands\MakeTemplateCommand;
use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Support\ScaffoldFiles;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ScaffoldingCommandsTest extends TestCase
{
    private string $root;
    private string $output;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nafcms-scaffold-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/host', 0777, true);
        file_put_contents($this->root . '/host/composer.json', '{"autoload":{"psr-4":{"Example\\\\Host\\\\":"src/"}}}');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir((string) $file) : unlink((string) $file);
        }
        rmdir($this->root);
    }

    public function testBlockCreatesClassAndViewAndRefusesEitherExistingTarget(): void
    {
        $command = new MakeBlockCommand($this->root . '/host');
        self::assertSame(0, $this->runCommand($command, ['example-card']));
        $file   = $this->root . '/host/src/Blocks/ExampleCardBlockType.php';
        $view   = $this->root . '/host/views/page/blocks/example_card.phtml';
        $source = (string) file_get_contents($file);
        self::assertStringContainsString('namespace Example\Host\Blocks;', $source);
        self::assertStringContainsString("return 'example-card';", $source);
        self::assertStringContainsString('\Naf\CMS\cms()->blockTypes()->add(new \Example\Host\Blocks\ExampleCardBlockType());', $this->output);
        self::assertFileExists($view);
        require $file;
        $className = ScaffoldFiles::hostNamespace($this->root . '/host') . '\\Blocks\\ExampleCardBlockType';
        $type      = new $className();
        self::assertInstanceOf(AbstractBlockType::class, $type);
        self::assertSame('Example Card', $type->label());
        self::assertSame(['main'], $type->regions());
        self::assertSame(['text' => ''], $type->defaults());
        self::assertSame('textarea', $type->fields()[0]['type']);
        self::assertSame('page.blocks.example_card', $type->view());
        unlink($file);
        self::assertSame(1, $this->runCommand($command, ['example-card']));
        self::assertFileDoesNotExist($file);
        self::assertSame(1, $this->runCommand($command, ['../escape']));
        unlink($view);
        file_put_contents($this->root . '/host/composer.json', '{}');
        self::assertSame(1, $this->runCommand($command, ['new-card']));
    }

    public function testTemplateHasAnchorSlotsAndRegistrationOutput(): void
    {
        $command = new MakeTemplateCommand($this->root . '/host');
        self::assertSame(0, $this->runCommand($command, ['split-page']));
        $file   = $this->root . '/host/views/page/templates/split_page.phtml';
        $source = (string) file_get_contents($file);
        self::assertStringContainsString('id="content" tabindex="-1"', $source);
        foreach (['site_header', 'main', 'site_footer'] as $slot) {
            self::assertStringContainsString("\$block('" . $slot . "')", $source);
            self::assertStringContainsString("->addBlock('" . $slot . "'", $this->output);
        }
        self::assertSame(1, $this->runCommand($command, ['split-page']));
        self::assertSame($source, file_get_contents($file));
    }

    public function testPluginSupportsDefaultAndExplicitPathsAndRefusesExistingTargets(): void
    {
        $command = new MakePluginCommand($this->root . '/host');
        self::assertSame(0, $this->runCommand($command, ['example/cards']));
        $composer = json_decode((string) file_get_contents($this->root . '/cards/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('naf-plugin', $composer['type']);
        self::assertSame('^0.0.3', $composer['require']['naf/cms']);
        self::assertSame(['Example\\Cards\\' => 'src/'], $composer['autoload']['psr-4']);
        self::assertFileExists($this->root . '/cards/bootstrap.php');
        self::assertFileExists($this->root . '/cards/src/views/.gitkeep');
        self::assertFileExists($this->root . '/cards/README.md');
        self::assertStringContainsString('plugins.php', $this->output);
        self::assertSame(1, $this->runCommand($command, ['example/cards']));
        self::assertSame(0, $this->runCommand($command, ['example/other', '--path=plugins/other']));
        self::assertFileExists($this->root . '/host/plugins/other/composer.json');
        self::assertSame(1, $this->runCommand($command, ['../bad']));
    }

    /** @param list<string> $arguments */
    private function runCommand(AbstractCommand $command, array $arguments): int
    {
        ob_start();

        try {
            return $command->run(new Input($arguments, $command->getDefinition()), new Output());
        } finally {
            $this->output = (string) ob_get_clean();
        }
    }
}
