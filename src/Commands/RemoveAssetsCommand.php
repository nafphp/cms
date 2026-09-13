<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CMS\Services\Assets\AssetPublisher;
use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;

final class RemoveAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nafcms:assets:remove';

    protected function configure(): void
    {
        $this->setTitle('Remove NafCMS Assets')
            ->setDescription('Remove only unchanged assets owned by the NafCMS publish manifest.');
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $removed = (new AssetPublisher())->remove();
        } catch (\Throwable $exception) {
            $output->writeLine($exception->getMessage(), 'error');
            return self::ERROR;
        }

        $output->writeLine(sprintf('Removed %d published NafCMS asset(s).', $removed), 'ok');
        return self::SUCCESS;
    }
}
