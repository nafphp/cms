<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

final class CustomLayoutOptions
{
    public const REGIONS = ['header', 'main', 'aside', 'footer'];
    public const SIZES = ['small', 'medium', 'large', 'full', 'aside', 'hero'];
    public const POSITIONS = ['stretch', 'start', 'center', 'end'];
    public const MAX_HEIGHTS = ['none', '320', '480', '640', '800', 'screen'];
    public const OVERFLOWS = ['visible', 'hidden', 'auto'];
    public const SURFACES = ['none', 'soft', 'card', 'accent'];

    /**
     * @param array<int, string> $allowed
     */
    public static function choice(mixed $value, array $allowed, string $fallback): string
    {
        if ($value !== null && !is_scalar($value) && !$value instanceof \Stringable) {
            return $fallback;
        }

        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
