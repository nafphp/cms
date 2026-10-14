<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Support\Docs\DefaultDocRenderers;
use Naf\CMS\Support\Docs\ReadmeDocBlockUpdater;
use Throwable;

final class DocsCheckCommand extends AbstractCommand
{
    public const string NAME = 'docs:check';

    protected function configure(): void
    {
        $this
            ->setTitle('Check Documentation')
            ->setDescription('Check whether README.md auto documentation blocks match NafCMS registries.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/naf docs:check');
            $output->writeLine('  Exits with code 1 when README.md is out of sync. Run vendor/bin/naf docs:sync to fix it.');

            return self::SUCCESS;
        }

        $readmePath = BASE_PATH . '/README.md';
        $current    = file_get_contents($readmePath);
        if ($current === false) {
            $output->writeLine('Could not read README.md.', 'error');

            return $this->fail();
        }

        $updater = new ReadmeDocBlockUpdater(DefaultDocRenderers::make(BASE_PATH));

        try {
            $synced = $updater->sync($current);
        } catch (Throwable $e) {
            $output->writeLine($e->getMessage(), 'error');
            $output->writeLine('Run `vendor/bin/naf docs:sync` after restoring the missing README.md markers.');

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
        $output->writeLine('Run `vendor/bin/naf docs:sync` and commit the README.md changes.');

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
