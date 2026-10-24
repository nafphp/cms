<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Support\RuntimePaths;
use Naf\CMS\Support\ScaffoldFiles;
use RuntimeException;

final class MakeTemplateCommand extends AbstractCommand
{
    public const string NAME = 'make:template';

    public function __construct(private readonly ?string $hostRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setTitle('Make Template')->setDescription('Create a host page template.')->addArgument('id');
    }

    public function run(Input $input, Output $output): int
    {
        $root = $this->hostRoot ?? RuntimePaths::hostRoot();
        $id   = trim((string) $input->getArgument('id'));

        try {
            ScaffoldFiles::validateId($id);
            $viewId = str_replace('-', '_', $id);
            $view   = <<<'PHP'
            <?php

            declare(strict_types=1);

            /** @var callable(string): string $block */

            ?>
            <div class="page-template-shell">
                <?= $block('site_header') ?>
                <main id="content" tabindex="-1" class="page-template-main"><?= $block('main') ?></main>
                <?= $block('site_footer') ?>
            </div>
            PHP;
            ScaffoldFiles::write([$root . '/views/page/templates/' . $viewId . '.phtml' => $view . "\n"]);
            $output->writeLine('Add to src/Resources/pages/page_templates.php (with Layout and Template imports):');
            $output->writeLine(strtr(<<<'PHP'
            Layout::define('{{id}}', '{{label}}', Template::create('{{view}}')->setParent('page'))
                ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'defaultContentType' => 'header'])
                ->addBlock('main', 'page.blocks.empty', ['label' => 'Main content', 'region' => 'main'])
                ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'defaultContentType' => 'footer']),
            PHP, ['{{id}}' => $id, '{{label}}' => ucwords(str_replace('-', ' ', $id)), '{{view}}' => $viewId]));
        } catch (RuntimeException $exception) {
            $output->writeLine($exception->getMessage(), 'error');

            return self::ERROR;
        }

        return self::SUCCESS;
    }
}
