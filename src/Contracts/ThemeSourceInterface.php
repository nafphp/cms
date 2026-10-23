<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Theme\Theme;

/**
 * Source for persisted themes.
 *
 * Hosts may replace the database-backed default with a design-system, file, or
 * empty source without coupling ThemeRegistry itself to persistence.
 * @api
 */
interface ThemeSourceInterface
{
    /** @return array<int, Theme> */
    public function themes(): array;
}
