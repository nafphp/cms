<?php

declare(strict_types=1);

namespace NixPHP\CMS\Commands;

use NixPHP\CMS\Support\Docs\DefaultDocRenderers;
use NixPHP\CMS\Support\Docs\ReadmeDocBlockUpdater;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class DocsCheckCommand extends AbstractCommand
{
    public const string NAME = 'docs:check';

    protected function configure(): void
    {
        $this
            ->setTitle('Check Documentation')
            ->setDescription('Check whether README.md auto documentation blocks match NixCMS registries.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/nix docs:check');
            $output->writeLine('  Exits with code 1 when README.md is out of sync. Run vendor/bin/nix docs:sync to fix it.');
            return self::SUCCESS;
        }

        $readmePath = BASE_PATH . '/README.md';
        $current = file_get_contents($readmePath);
        if ($current === false) {
            $output->writeLine('Could not read README.md.', 'error');
            return $this->fail();
        }

        $updater = new ReadmeDocBlockUpdater(DefaultDocRenderers::make(BASE_PATH));
        try {
            $synced = $updater->sync($current);
        } catch (\Throwable $e) {
            $output->writeLine($e->getMessage(), 'error');
            $output->writeLine('Run `vendor/bin/nix docs:sync` after restoring the missing README.md markers.');
            return $this->fail();
        }
        $normalizedCurrent = str_replace("\r\n", "\n", $current);

        if ($synced === $normalizedCurrent) {
            $output->writeLine('README.md documentation blocks are up to date.', 'ok');
            return self::SUCCESS;
        }

        $changedBlocks = $updater->changedBlockNames($current);
        $output->writeLine('README.md documentation blocks are out of sync.', 'error');
        if ($changedBlocks !== []) {
            $output->writeLine('Changed blocks: ' . implode(', ', $changedBlocks));
        }
        $output->writeLine('Run `vendor/bin/nix docs:sync` and commit the README.md changes.');

        return $this->fail();
    }

    private function fail(): int
    {
        if (!defined('PHPUNIT_RUNNING')) {
            exit(self::ERROR);
        }

        return self::ERROR;
    }
}
