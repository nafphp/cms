<?php

declare(strict_types=1);

namespace NixCMS\Commands;

use NixCMS\Support\Docs\DefaultDocRenderers;
use NixCMS\Support\Docs\ReadmeDocBlockUpdater;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class DocsSyncCommand extends AbstractCommand
{
    public const string NAME = 'docs:sync';

    protected function configure(): void
    {
        $this
            ->setTitle('Sync Documentation')
            ->setDescription('Regenerate README.md auto documentation blocks from NixCMS registries.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix docs:sync');
            $output->writeLine('  Rewrites README.md sections between <!-- nix:auto:* --> markers.');
            return self::SUCCESS;
        }

        $readmePath = BASE_PATH . '/README.md';
        $current = file_get_contents($readmePath);
        if ($current === false) {
            $output->writeLine('Could not read README.md.', 'error');
            return self::ERROR;
        }

        $updater = new ReadmeDocBlockUpdater(DefaultDocRenderers::make(BASE_PATH));
        try {
            $synced = $updater->sync($current);
        } catch (\Throwable $e) {
            $output->writeLine($e->getMessage(), 'error');
            return self::ERROR;
        }

        if ($synced === str_replace("\r\n", "\n", $current)) {
            $output->writeLine('README.md documentation blocks are already up to date.', 'ok');
            return self::SUCCESS;
        }

        if (file_put_contents($readmePath, $synced) === false) {
            $output->writeLine('Could not write README.md.', 'error');
            return self::ERROR;
        }

        $output->writeLine('README.md documentation blocks synced.', 'ok');
        return self::SUCCESS;
    }
}
