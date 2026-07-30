<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

use NixPHP\CMS\Content\BlockViewRegistry;
use NixPHP\CMS\Content\Page\Block\BlockTypeInterface;
use NixPHP\CMS\Page\Layout;
use NixPHP\CMS\Support\RuntimePaths;
use function NixPHP\CMS\cms;

final class IntegrityChecker
{
    private const array IGNORED_BLOCK_VIEWS = [
        'empty.phtml' => true,
        'placeholder.phtml' => true,
        'welcome.phtml' => true,
    ];

    /**
     * @param array<int|string, BlockTypeInterface>|null $blockTypes
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?array $blockTypes = null,
    )
    {
    }

    /**
     * @return array<int, array{file: string, line: int, message: string}>
     */
    public function check(): array
    {
        $issues = [];
        $blockTypes = $this->blockTypes();
        $blockViews = new BlockViewRegistry();
        $templateFile = RuntimePaths::resource($this->basePath, 'pages/page_templates.php');
        $templates = $this->pageTemplates($templateFile);

        $issues = [
            ...$issues,
            ...$this->checkBlockViews($blockTypes, $blockViews),
            ...$this->checkTemplateViews($templates, $templateFile),
            ...$this->checkTemplateBlockReferences($templates, $templateFile, array_keys($blockTypes)),
        ];

        usort($issues, static function (array $a, array $b): int {
            return [$a['file'], $a['line'], $a['message']] <=> [$b['file'], $b['line'], $b['message']];
        });

        return $issues;
    }

    /**
     * @return array<string, BlockTypeInterface>
     */
    private function blockTypes(): array
    {
        $registered = [];

        foreach ($this->blockTypes ?? cms()->blockTypes()->all() as $type) {
            if (!$type instanceof BlockTypeInterface) {
                continue;
            }

            $registered[$type->id()] = $type;
        }

        return $registered;
    }

    /**
     * @param array<string, BlockTypeInterface> $blockTypes
     * @return array<int, array{file: string, line: int, message: string}>
     */
    private function checkBlockViews(array $blockTypes, BlockViewRegistry $blockViews): array
    {
        $issues = [];
        $mappings = $blockViews->all();
        $referencedViews = [];

        foreach ($blockTypes as $id => $type) {
            $reflection = new \ReflectionObject($type);
            $file = (string) $reflection->getFileName();
            $line = $reflection->getStartLine();

            if (!isset($mappings[$id])) {
                $issues[] = $this->issue($file, $line, sprintf(
                    'BlockType "%s" is registered but has no explicit BlockViewRegistry mapping.',
                    $id,
                ));
                continue;
            }

            $view = $mappings[$id];
            $viewFile = $this->viewFile($view);
            $referencedViews[$viewFile] = true;

            if (!is_file($viewFile)) {
                $registryFile = $this->basePath . '/src/Content/BlockViewRegistry.php';
                $issues[] = $this->issue($registryFile, $this->lineOf($registryFile, $id), sprintf(
                    'BlockType "%s" maps to missing view "%s" (%s).',
                    $id,
                    $view,
                    $this->relative($viewFile),
                ));
            }
        }

        foreach ($this->blockViewFiles() as $viewFile) {
            if (!isset($referencedViews[$viewFile])) {
                $issues[] = $this->issue($viewFile, 1, sprintf(
                    'Block view "%s" is not referenced by any registered BlockType.',
                    $this->relative($viewFile),
                ));
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @return array<int, array{file: string, line: int, message: string}>
     */
    private function checkTemplateViews(array $templates, string $templateFile): array
    {
        $issues = [];
        $registeredViews = [];

        foreach ($templates as $template) {
            $id = (string) ($template['id'] ?? '');
            $view = (string) ($template['view'] ?? '');
            if ($view === '') {
                $issues[] = $this->issue($templateFile, $this->lineOf($templateFile, $id), sprintf(
                    'Page template "%s" does not define a view.',
                    $id !== '' ? $id : '(missing id)',
                ));
                continue;
            }

            $viewFile = $this->viewFile($view);
            $registeredViews[$viewFile] = $id;
            if (!is_file($viewFile)) {
                $issues[] = $this->issue($templateFile, $this->lineOf($templateFile, $view), sprintf(
                    'Page template "%s" maps to missing view "%s" (%s).',
                    $id,
                    $view,
                    $this->relative($viewFile),
                ));
            }
        }

        foreach ($this->templateViewFiles() as $viewFile) {
            if (!isset($registeredViews[$viewFile])) {
                $issues[] = $this->issue($viewFile, 1, sprintf(
                    'Page template view "%s" is not registered in %s.',
                    $this->relative($viewFile),
                    $this->relative($templateFile),
                ));
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @param array<int, string> $blockTypeIds
     * @return array<int, array{file: string, line: int, message: string}>
     */
    private function checkTemplateBlockReferences(array $templates, string $templateFile, array $blockTypeIds): array
    {
        $issues = [];
        $knownBlockTypes = array_fill_keys($blockTypeIds, true);

        foreach ($templates as $template) {
            $templateId = (string) ($template['id'] ?? '(missing id)');
            foreach (is_array($template['blocks'] ?? null) ? $template['blocks'] : [] as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $blockId = (string) ($block['id'] ?? '(missing id)');
                foreach (is_array($block['accepts'] ?? null) ? $block['accepts'] : [] as $accepted) {
                    $accepted = (string) $accepted;
                    if (!isset($knownBlockTypes[$accepted])) {
                        $issues[] = $this->issue($templateFile, $this->lineOf($templateFile, $accepted), sprintf(
                            'Page template "%s" block "%s" accepts unknown BlockType "%s".',
                            $templateId,
                            $blockId,
                            $accepted,
                        ));
                    }
                }

                if (array_key_exists('defaultContentType', $block)) {
                    $defaultContentType = (string) $block['defaultContentType'];
                    if (!isset($knownBlockTypes[$defaultContentType])) {
                        $issues[] = $this->issue($templateFile, $this->lineOf($templateFile, "'defaultContentType' => '" . $defaultContentType . "'"), sprintf(
                            'Page template "%s" block "%s" defaults to unknown BlockType "%s".',
                            $templateId,
                            $blockId,
                            $defaultContentType,
                        ));
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageTemplates(string $templateFile): array
    {
        if (!is_file($templateFile)) {
            return [];
        }

        $templates = require $templateFile;
        return is_array($templates) ? Layout::definitions($templates) : [];
    }

    /**
     * @return array<int, string>
     */
    private function blockViewFiles(): array
    {
        $files = glob(RuntimePaths::views($this->basePath, 'page/blocks') . '/*.phtml') ?: [];

        return array_values(array_filter($files, static function (string $file): bool {
            return !isset(self::IGNORED_BLOCK_VIEWS[basename($file)]);
        }));
    }

    /**
     * @return array<int, string>
     */
    private function templateViewFiles(): array
    {
        return glob(RuntimePaths::views($this->basePath, 'page/templates') . '/*.phtml') ?: [];
    }

    private function viewFile(string $view): string
    {
        $path = str_replace('.', '/', trim($view, '/'));
        return RuntimePaths::views($this->basePath, $path . '.phtml');
    }

    /**
     * @return array{file: string, line: int, message: string}
     */
    private function issue(string $file, int $line, string $message): array
    {
        return [
            'file' => $this->relative($file),
            'line' => max(1, $line),
            'message' => $message,
        ];
    }

    private function lineOf(string $file, string $needle): int
    {
        if ($needle === '' || !is_file($file)) {
            return 1;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return 1;
        }

        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index + 1;
            }
        }

        return 1;
    }

    private function relative(string $file): string
    {
        $prefix = rtrim($this->basePath, '/') . '/';
        return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
    }
}
