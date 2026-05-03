<?php

declare(strict_types=1);

namespace NixCMS\Commands;

use NixCMS\Support\Docs\IntegrityChecker;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class IntegrityCheckCommand extends AbstractCommand
{
    public const string NAME = 'integrity:check';

    protected function configure(): void
    {
        $this
            ->setTitle('Check NixCMS Integrity')
            ->setDescription('Check block type, block view, and page template registry invariants.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix integrity:check');
            $output->writeLine('  Exits with code 1 when block/template registries drift from filesystem views.');
            return self::SUCCESS;
        }

        $issues = (new IntegrityChecker(BASE_PATH))->check();
        if ($issues === []) {
            $output->writeLine('NixCMS integrity checks passed.', 'ok');
            return self::SUCCESS;
        }

        $output->writeLine('NixCMS integrity checks found drift:', 'error');
        foreach ($issues as $issue) {
            $output->writeLine(sprintf(
                '  - %s:%d %s',
                $issue['file'],
                $issue['line'],
                $issue['message'],
            ));
        }

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
