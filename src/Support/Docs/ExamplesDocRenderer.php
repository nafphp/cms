<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

use Naf\CMS\Support\Docs\DocBlockRenderer;
use Naf\CMS\Support\Docs\MarkdownTable;

final class ExamplesDocRenderer implements DocBlockRenderer
{
    public function __construct(private readonly string $examplesPath)
    {
    }

    public function name(): string
    {
        return 'examples';
    }

    public function render(): string
    {
        $directories = glob($this->examplesPath . '/*', GLOB_ONLYDIR) ?: [];
        sort($directories);

        return MarkdownTable::render(
            ['Example', 'Shows'],
            array_map(
                fn(string $directory): array => [
                    '[' . basename($directory) . '](examples/' . basename($directory) . '/)',
                    $this->readOneLiner($directory . '/README.md'),
                ],
                $directories
            )
        );
    }

    private function readOneLiner(string $readme): string
    {
        if (!is_file($readme)) {
            return 'No README.md yet.';
        }

        foreach (file($readme, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            return $line;
        }

        return 'No summary yet.';
    }
}
