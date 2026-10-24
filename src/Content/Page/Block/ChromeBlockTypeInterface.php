<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block;

/**
 * A block type that may fill the header or footer area of a custom layout
 *
 * In a custom layout the header and footer areas are site chrome: only block
 * types that name the area here may be placed there. Template slots are not
 * affected; they follow regions() and their own `accepts`.
 *
 * @api
 */
interface ChromeBlockTypeInterface
{
    /**
     * @return array<int, string> Any of "header" and "footer"
     */
    public function chromeRegions(): array;
}
