<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Support\RuntimePaths;
use Naf\CMS\Support\ScaffoldFiles;
use RuntimeException;

final class MakePluginCommand extends AbstractCommand
{
    public const string NAME = 'make:plugin';

    public function __construct(private readonly ?string $hostRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setTitle('Make Plugin')->setDescription('Create a Composer plugin skeleton.')->addArgument('id')->addOption('path', null, true);
    }

    public function run(Input $input, Output $output): int
    {
        $root = $this->hostRoot ?? RuntimePaths::hostRoot();
        $id   = trim((string) $input->getArgument('id'));

        try {
            if (preg_match('/^([a-z][a-z0-9]*(?:-[a-z0-9]+)*)\/([a-z][a-z0-9]*(?:-[a-z0-9]+)*)$/D', $id, $parts) !== 1) {
                throw new RuntimeException('Plugin name must use vendor/name with lowercase letters, digits and hyphens.');
            }
            $option = $input->getOption('path');
            $path   = is_string($option) && $option !== '' ? $option : '../' . $parts[2];
            if (!str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
                $path = $root . '/' . $path;
            }
            if (file_exists($path) || is_link($path)) {
                throw new RuntimeException('Target already exists: ' . $path);
            }
            $prefix   = ScaffoldFiles::studly($parts[1]) . '\\' . ScaffoldFiles::studly($parts[2]) . '\\';
            $composer = json_encode([
                'name'     => $id,
                'type'     => 'naf-plugin',
                'require'  => ['naf/cms' => '^0.0.3'],
                'autoload' => ['psr-4' => [$prefix => 'src/']],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            ScaffoldFiles::write([
                $path . '/composer.json'      => $composer . "\n",
                $path . '/bootstrap.php'      => "<?php\n\ndeclare(strict_types=1);\n\n// Register pages, block types, commands and tools here through NafCMS registries.\n",
                $path . '/src/views/.gitkeep' => '',
                $path . '/README.md'          => '# ' . $id . "\n\nA NafCMS extension. Register features explicitly in bootstrap.php.\n",
            ]);
            $output->writeLine('Add a Composer path repository in the host for ' . $path . ', then require ' . $id . '.');
            $output->writeLine('Add ' . $id . ' to the host plugins.php.');
        } catch (RuntimeException $exception) {
            $output->writeLine($exception->getMessage(), 'error');

            return self::ERROR;
        }

        return self::SUCCESS;
    }
}
