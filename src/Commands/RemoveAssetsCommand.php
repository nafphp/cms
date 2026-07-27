<?php

declare(strict_types=1);

namespace NixPHP\CMS\Commands;

use NixPHP\CMS\Services\Assets\AssetPublisher;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

final class RemoveAssetsCommand extends AbstractCommand
{
    public const string NAME = 'nixcms:assets:remove';

    protected function configure(): void
    {
        $this->setTitle('Remove NixCMS Assets')
            ->setDescription('Remove only unchanged assets owned by the NixCMS publish manifest.');
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $removed = (new AssetPublisher())->remove();
        } catch (\Throwable $exception) {
            $output->writeLine($exception->getMessage(), 'error');
            return self::ERROR;
        }

        $output->writeLine(sprintf('Removed %d published NixCMS asset(s).', $removed), 'ok');
        return self::SUCCESS;
    }
}
