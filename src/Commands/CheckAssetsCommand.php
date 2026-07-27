<?php

declare(strict_types=1);

namespace src\Commands;

use src\Services\Assets\AssetPublisher;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class CheckAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nixcms:assets:check';

    protected function configure(): void
    {
        $this->setTitle('Check NixCMS Assets')
            ->setDescription('Report missing, outdated, stale, or conflicting published assets.');
    }

    public function run(Input $input, Output $output): int
    {
        $status = (new AssetPublisher())->status();
        if ($status['current']) {
            $output->writeLine('Published NixCMS assets are current.', 'ok');
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
