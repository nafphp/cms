<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CMS\Support\Docs\IntegrityChecker;
use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;

final class IntegrityCheckCommand extends AbstractCommand
{
    public const string NAME = 'integrity:check';

    protected function configure(): void
    {
        $this
            ->setTitle('Check NafCMS Integrity')
            ->setDescription('Check block type, block view, and page template registry invariants.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/naf integrity:check');
            $output->writeLine('  Exits with code 1 when block/template registries drift from filesystem views.');
            return self::SUCCESS;
        }

        $issues = (new IntegrityChecker(BASE_PATH))->check();
        if ($issues === []) {
            $output->writeLine('NafCMS integrity checks passed.', 'ok');
            return self::SUCCESS;
        }

        $output->writeLine('NafCMS integrity checks found drift:', 'error');
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
