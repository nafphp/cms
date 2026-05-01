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
    public const string NAME = 'db:install';

    protected function configure(): void
    {
        $this
            ->setTitle('Install Database')
            ->setDescription('Create the database (if missing) and run all pending migrations.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix db:install');
            $output->writeLine('  Runs db:create followed by db:migrate up.');
            return self::SUCCESS;
        }

        $createCommand = new CreateDatabaseCommand();
        $createInput = new Input([], $createCommand->getDefinition());
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
