<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

use Naf\CMS\Support\Docs\DocBlockRenderer;

final class ReadmeDocBlockUpdater
{
    /**
     * @param iterable<DocBlockRenderer> $renderers
     */
    public function __construct(private readonly iterable $renderers)
    {
    }

    public function sync(string $markdown): string
    {
        $markdown = $this->normalizeLineEndings($markdown);

        foreach ($this->renderers as $renderer) {
            $markdown = $this->replaceBlock($markdown, $renderer);
        }

        return rtrim($markdown) . "\n";
    }

    /**
     * @return array<int, string>
     */
    public function changedBlockNames(string $markdown): array
    {
        $markdown = $this->normalizeLineEndings($markdown);
        $changed = [];

        foreach ($this->renderers as $renderer) {
            if (trim($this->extractBlockBody($markdown, $renderer->name())) !== trim($this->renderBody($renderer))) {
                $changed[] = $renderer->name();
            }
        }

        return $changed;
    }

    private function replaceBlock(string $markdown, DocBlockRenderer $renderer): string
    {
        $name = preg_quote($renderer->name(), '/');
        $pattern = sprintf('/(<!-- naf:auto:%s -->).*?(<!-- \/naf:auto:%s -->)/s', $name, $name);

        $updated = preg_replace_callback(
            $pattern,
            fn(array $matches): string => $matches[1] . "\n" . $this->renderBody($renderer) . "\n" . $matches[2],
            $markdown,
            1,
            $count
        );

        if ($updated === null) {
            throw new \RuntimeException(sprintf('Could not update docs block "%s".', $renderer->name()));
        }

        if ($count !== 1) {
            throw new \RuntimeException(sprintf('README.md is missing docs block marker "%s".', $renderer->name()));
        }

        return $updated;
    }

    private function extractBlockBody(string $markdown, string $name): string
    {
        $name = preg_quote($name, '/');
        $pattern = sprintf('/<!-- naf:auto:%s -->(.*?)<!-- \/naf:auto:%s -->/s', $name, $name);

        if (preg_match($pattern, $markdown, $matches) !== 1) {
            return '';
        }

        return (string) $matches[1];
    }

    private function renderBody(DocBlockRenderer $renderer): string
    {
        return "<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->\n\n"
            . trim($renderer->render());
    }

    private function normalizeLineEndings(string $markdown): string
    {
        return str_replace("\r\n", "\n", $markdown);
    }
}
