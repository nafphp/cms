<?php

declare(strict_types=1);

namespace NixCMS\Commands;

use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;
use NixPHP\Database\Commands\MigrateCommand;
use function NixPHP\app;

class InstallDatabaseCommand extends AbstractCommand
{
    public const string NAME = 'install:database';

    protected function configure(): void
    {
        $this
            ->setTitle('Install Database')
            ->setDescription('Create the database (if missing) and run all pending migrations.')
            ->addOption('force')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine(sprintf('    php bin/nix %s', static::NAME));
            $output->writeLine(sprintf('    php bin/nix %s --force', static::NAME));
            $output->writeLine('  Creates the configured database if needed, then runs db:migrate up.');
            $output->writeLine('  --force drops the configured database before recreating it.');
            return self::SUCCESS;
        }

        $createCommand = new CreateDatabaseCommand();
        $createInput = new Input($input->getOption('force') === true ? ['--force'] : [], $createCommand->getDefinition());
        $createResult = $createCommand->run($createInput, $output);

        if ($createResult !== self::SUCCESS) {
            return $createResult;
        }

        $output->writeEmptyLine();

        /** @var MigrateCommand $migrateCommand */
        $migrateCommand = app()->container()->make(MigrateCommand::class);
        $migrateInput = new Input(['up'], $migrateCommand->getDefinition());

        return $migrateCommand->run($migrateInput, $output);
    }
}
