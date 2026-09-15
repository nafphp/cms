<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Services\Assets\AssetPublisher;

final class CheckAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nafcms:assets:check';

    protected function configure(): void
    {
        $this->setTitle('Check NafCMS Assets')
            ->setDescription('Report missing, outdated, stale, or conflicting published assets.');
    }

    public function run(Input $input, Output $output): int
    {
        $status = (new AssetPublisher())->status();
        if ($status['current']) {
            $output->writeLine('Published NafCMS assets are current.', 'ok');

            return self::SUCCESS;
        }

        foreach (['missing', 'outdated', 'stale', 'conflicts'] as $kind) {
            if ($status[$kind] !== []) {
                $output->writeLine(ucfirst($kind) . ': ' . implode(', ', $status[$kind]), 'warning');
            }
        }

        return self::ERROR;
    }
}
