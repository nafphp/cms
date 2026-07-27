<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Support\Docs\DocBlockRenderer;
use NixPHP\CMS\Support\Docs\MarkdownTable;
use function NixPHP\CMS\cms;

final class PagesDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'pages';
    }

    public function render(): string
    {
        $pages = cms()->pages()->all();
        uasort($pages, static fn(Page $a, Page $b): int => [$a->path(), $a->id()] <=> [$b->path(), $b->id()]);

        return MarkdownTable::render(
            ['ID', 'Path', 'Layout', 'Permissions', 'Data handler'],
            array_map(
                fn(Page $page): array => [
                    '`' . $page->id() . '`',
                    '`' . $page->path() . '`',
                    $page->layout() !== null ? '`' . $page->layout() . '`' : '',
                    $this->permissions($page),
                    $page->dataHandlers() !== [],
                ],
                array_values($pages)
            )
        );
    }

    private function permissions(Page $page): string
    {
        $permissions = array_map(
            static fn(Permission $permission): string => '`' . $permission->value . '`',
            $page->requiredPermissions()
        );

        if ($permissions === []) {
            return '';
        }

        $prefix = $page->requiresAnyPermission() ? 'any of ' : '';
        return $prefix . implode(', ', $permissions);
    }
}
