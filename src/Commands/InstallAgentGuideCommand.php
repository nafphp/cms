<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Services\Developer\AgentGuideInstaller;

use function Naf\app;

final class InstallAgentGuideCommand extends AbstractCommand
{
    public const string NAME = 'nafcms:agents:install';

    protected function configure(): void
    {
        $this->setTitle('Install NafCMS Agent Guide')
            ->setDescription('Create the host AGENTS.md without overwriting user-owned instructions.')
            ->addOption('update')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage: vendor/bin/naf nafcms:agents:install [--update]');
            $output->writeLine('  Existing unmarked AGENTS.md files are never overwritten.');

            return self::SUCCESS;
        }

        $installer = new AgentGuideInstaller(
            dirname(__DIR__, 2) . '/docs/templates/SHIPPED_AGENTS.md',
            rtrim((string) app()->getBasePath(), '/') . '/AGENTS.md',
        );
        $result = $installer->install($input->getOption('update') === true);

        return match ($result['status']) {
            'created'         => $this->success($output, 'Created host AGENTS.md.'),
            'updated'         => $this->success($output, 'Updated the NafCMS-managed host AGENTS.md.'),
            'current'         => $this->success($output, 'Host AGENTS.md is current.'),
            'update_required' => $this->failure($output, 'A managed AGENTS.md exists; rerun with --update to refresh it.'),
            default           => $this->failure($output, 'A user-owned AGENTS.md already exists and was not changed.'),
        };
    }

    private function success(Output $output, string $message): int
    {
        $output->writeLine($message, 'ok');

        return self::SUCCESS;
    }

    private function failure(Output $output, string $message): int
    {
        $output->writeLine($message, 'warning');

        return self::ERROR;
    }
}
