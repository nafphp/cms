<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

use Naf\CMS\Support\Docs\ApiDocRenderer;
use Naf\CMS\Support\Docs\CmsApiDocRenderer;
use Naf\CMS\Support\Docs\ContentTypesDocRenderer;
use Naf\CMS\Support\Docs\DocBlockRenderer;
use Naf\CMS\Support\Docs\ExamplesDocRenderer;
use Naf\CMS\Support\Docs\McpDocRenderer;
use Naf\CMS\Support\Docs\PagesDocRenderer;
use Naf\CMS\Support\Docs\PermissionsDocRenderer;

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
