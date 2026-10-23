<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use function Naf\CMS\cms;

/**
 * @deprecated Use cms()->pageTemplates() instead; kept for existing host code.
 */
final class PageTemplateCatalog
{
    /** @return array<int, array<string, mixed>> */
    public function codeDefinitions(): array
    {
        return cms()->pageTemplates()->definitions();
    }
}
