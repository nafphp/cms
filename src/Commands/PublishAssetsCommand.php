<?php

declare(strict_types=1);

namespace NixPHP\CMS\Commands;

use NixPHP\CMS\Services\Assets\AssetPublisher;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class PublishAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nixcms:assets:publish';

    protected function configure(): void
    {
        $this->setTitle('Publish NixCMS Assets')
            ->setDescription('Publish versioned NixCMS assets into the host public directory.');
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $result = (new AssetPublisher())->publish();
        } catch (\Throwable $exception) {
            $output->writeLine($exception->getMessage(), 'error');
            return self::ERROR;
        }

        $output->writeLine(sprintf(
            'Published %d asset(s), removed %d stale asset(s), version %s.',
            $result['published'],
            $result['removed'],
            $result['version'],
        ), 'ok');

        return self::SUCCESS;
    }
}
