<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Support\RuntimePaths;
use Naf\CMS\Support\ScaffoldFiles;
use RuntimeException;

final class MakeBlockCommand extends AbstractCommand
{
    public const string NAME = 'make:block';

    public function __construct(private readonly ?string $hostRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setTitle('Make Block')->setDescription('Create a host block type and its view.')->addArgument('id');
    }

    public function run(Input $input, Output $output): int
    {
        $root = $this->hostRoot ?? RuntimePaths::hostRoot();
        $id   = trim((string) $input->getArgument('id'));

        try {
            ScaffoldFiles::validateId($id);
            $namespace = ScaffoldFiles::hostNamespace($root) . '\Blocks';
            $class     = ScaffoldFiles::studly($id) . 'BlockType';
            $label     = ucwords(str_replace('-', ' ', $id));
            $source    = strtr(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{namespace}};

            use Naf\CMS\Content\Page\Block\AbstractBlockType;

            final class {{class}} extends AbstractBlockType
            {
                public function id(): string
                {
                    return '{{id}}';
                }

                public function label(): string
                {
                    return '{{label}}';
                }

                public function regions(): array
                {
                    return ['main'];
                }

                public function defaults(): array
                {
                    return ['text' => ''];
                }

                public function fields(): array
                {
                    return [['name' => 'text', 'label' => 'Text', 'type' => 'textarea']];
                }
            }
            PHP, ['{{namespace}}' => $namespace, '{{class}}' => $class, '{{id}}' => $id, '{{label}}' => $label]);
            $view = <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Naf\View\s;

            $text = (string) ($content['props']['text'] ?? $text ?? '');

            ?>
            <section class="page-block"><?= s($text ?? '') ?></section>
            PHP;
            ScaffoldFiles::write([
                $root . '/src/Blocks/' . $class . '.php'                              => $source . "\n",
                $root . '/views/page/blocks/' . str_replace('-', '_', $id) . '.phtml' => $view . "\n",
            ]);
            $output->writeLine('Register in src/services.php:');
            $output->writeLine('\Naf\CMS\cms()->blockTypes()->add(new \\' . $namespace . '\\' . $class . '());');
        } catch (RuntimeException $exception) {
            $output->writeLine($exception->getMessage(), 'error');

            return self::ERROR;
        }

        return self::SUCCESS;
    }
}
