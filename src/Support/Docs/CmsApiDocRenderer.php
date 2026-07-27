<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

use NixPHP\CMS\Support\Cms;
use ReflectionClass;
use ReflectionMethod;
use NixPHP\CMS\Support\Docs\DocBlockRenderer;
use NixPHP\CMS\Support\Docs\MarkdownTable;
use function NixPHP\CMS\cms;

final class CmsApiDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'cms';
    }

    public function render(): string
    {
        $methods = array_filter(
            (new ReflectionClass(Cms::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn(ReflectionMethod $method): bool => $method->getName() !== '__construct'
        );

        usort(
            $methods,
            fn(ReflectionMethod $a, ReflectionMethod $b): int => $this->sortRank($a->getName()) <=> $this->sortRank($b->getName())
                ?: $a->getName() <=> $b->getName()
        );

        return MarkdownTable::render(
            ['Entry point', 'Purpose'],
            array_map(
                fn(ReflectionMethod $method): array => [
                    '`' . $this->signature($method->getName()) . '`',
                    $this->description($method->getName()),
                ],
                $methods
            )
        );
    }

    private function signature(string $method): string
    {
        return match ($method) {
            'content' => 'cms()->content(Model::class)',
            'scope' => 'cms()->scope(Scope::ADMIN)',
            'storage' => 'cms()->storage(Model::class)',
            default => sprintf('cms()->%s()', $method),
        };
    }

    private function description(string $method): string
    {
        return [
            'pages' => 'Renderable HTTP pages and page data handlers.',
            'scope' => 'Scope-bound CMS APIs; defaults to Scope::FRONTEND.',
            'api' => 'JSON/API/action endpoints.',
            'contentTypes' => 'Registered CMS content types.',
            'content' => 'Resolve a content API by model class.',
            'articles' => 'Convenience API for the Article content type.',
            'forms' => 'Convenience API for the Form content type.',
            'media' => 'Convenience API for the Media content type.',
            'navigation' => 'Site navigation content and navigation metadata registration.',
            'storage' => 'Criteria-based CRUD for one ORM entity.',
            'visualizations' => 'Registered chart definitions and rendering.',
            'blocks' => 'Page-builder block type to view mappings.',
            'blockTypes' => 'Page-builder block metadata for the admin builder.',
            'themes' => 'Frontend theme assets and builder-visible surfaces.',
            'languages' => 'Enabled, available, and discovered language codes.',
            'settings' => 'Resolved runtime settings and admin-editable setting metadata.',
        ][$method] ?? 'Public CMS helper entry point.';
    }

    private function sortRank(string $method): int
    {
        $order = [
            'pages',
            'scope',
            'api',
            'contentTypes',
            'content',
            'articles',
            'forms',
            'media',
            'navigation',
            'storage',
            'visualizations',
            'blocks',
            'blockTypes',
            'themes',
            'languages',
            'settings',
        ];

        $rank = array_search($method, $order, true);
        return $rank === false ? 999 : (int) $rank;
    }
}
