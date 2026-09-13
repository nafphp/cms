<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CMS\Support\Docs\DefaultDocRenderers;
use Naf\CMS\Support\Docs\ReadmeDocBlockUpdater;
use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;

final class DocsSyncCommand extends AbstractCommand
{
    public const string NAME = 'docs:sync';

    protected function configure(): void
    {
        $this
            ->setTitle('Sync Documentation')
            ->setDescription('Regenerate README.md auto documentation blocks from NafCMS registries.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/naf docs:sync');
            $output->writeLine('  Rewrites README.md sections between <!-- naf:auto:* --> markers.');
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
