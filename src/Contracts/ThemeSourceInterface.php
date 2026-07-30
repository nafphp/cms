<?php

declare(strict_types=1);

namespace NixPHP\CMS\Contracts;

use NixPHP\CMS\Theme\Theme;

/**
 * Source for persisted themes.
 *
 * Hosts may replace the database-backed default with a design-system, file, or
 * empty source without coupling ThemeRegistry itself to persistence.
 */
interface ThemeSourceInterface
{
    /** @return array<int, Theme> */
    public function themes(): array;
}
