<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

use NixPHP\CMS\Support\Docs\ApiDocRenderer;
use NixPHP\CMS\Support\Docs\CmsApiDocRenderer;
use NixPHP\CMS\Support\Docs\ContentTypesDocRenderer;
use NixPHP\CMS\Support\Docs\DocBlockRenderer;
use NixPHP\CMS\Support\Docs\ExamplesDocRenderer;
use NixPHP\CMS\Support\Docs\McpDocRenderer;
use NixPHP\CMS\Support\Docs\PagesDocRenderer;
use NixPHP\CMS\Support\Docs\PermissionsDocRenderer;

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
