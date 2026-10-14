<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

final class DefaultDocRenderers
{
    /**
     * @return array<int, DocBlockRenderer>
     */
    public static function make(string $basePath): array
    {
        return [
            new CmsApiDocRenderer(),
            new PagesDocRenderer(),
            new ApiDocRenderer(),
            new ContentTypesDocRenderer(),
            new PermissionsDocRenderer(),
            new McpDocRenderer(),
            new ExamplesDocRenderer($basePath . '/examples'),
        ];
    }
}
