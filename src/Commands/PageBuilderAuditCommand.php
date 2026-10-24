<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Page\PageBuilderAudit;
use PDO;
use Throwable;

use function Naf\Database\database;

final class PageBuilderAuditCommand extends AbstractCommand
{
    public const string NAME = 'page-builder:audit';

    public function __construct(private readonly ?PageBuilderAudit $audit = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setTitle('Audit Page Builder Content')
            ->setDescription('List stored pages whose blocks break the current page-builder rules.');
    }

    public function run(Input $input, Output $output): int
    {
        if ($this->audit === null && !$this->hasDatabase()) {
            $output->writeLine('No database connection: stored pages cannot be audited.', 'error');

            return self::ERROR;
        }

        $findings = ($this->audit ?? new PageBuilderAudit())->run();
        if ($findings === []) {
            $output->writeLine('All stored pages follow the current page-builder rules.', 'ok');

            return self::SUCCESS;
        }

        $output->writeLine(sprintf('%d page(s) break the current page-builder rules:', count($findings)), 'warning');
        foreach ($findings as $finding) {
            $output->writeLine(sprintf(
                '  #%d %s%s "%s"',
                $finding['id'],
                $finding['path'],
                $finding['language'] !== '' ? ' [' . $finding['language'] . ']' : '',
                $finding['title'],
            ));
            foreach ($finding['problems'] as $problem) {
                $output->writeLine('    - ' . $problem);
            }
        }

        return self::ERROR;
    }

    private function hasDatabase(): bool
    {
        try {
            return database() instanceof PDO;
        } catch (Throwable) {
            return false;
        }
    }
}
