<?php

declare(strict_types=1);

namespace src\Support\Docs;

use src\Support\Docs\ApiDocRenderer;
use src\Support\Docs\CmsApiDocRenderer;
use src\Support\Docs\ContentTypesDocRenderer;
use src\Support\Docs\DocBlockRenderer;
use src\Support\Docs\ExamplesDocRenderer;
use src\Support\Docs\McpDocRenderer;
use src\Support\Docs\PagesDocRenderer;
use src\Support\Docs\PermissionsDocRenderer;

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
