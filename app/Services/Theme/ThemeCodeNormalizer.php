<?php

declare(strict_types=1);

namespace NixCMS\Services\Theme;

final class ThemeCodeNormalizer
{
    public function normalize(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }
}
