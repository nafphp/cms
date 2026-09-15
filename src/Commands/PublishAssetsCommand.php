<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Services\Assets\AssetPublisher;
use Throwable;

final class PublishAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nafcms:assets:publish';

    protected function configure(): void
    {
        $this->setTitle('Publish NafCMS Assets')
            ->setDescription('Publish versioned NafCMS assets into the host public directory.');
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $result = (new AssetPublisher())->publish();
        } catch (Throwable $exception) {
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
