<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

/** JSON embedded in an HTML script element must never contain literal markup delimiters. */
final class ScriptJson
{
    public static function encode(mixed $value, int $flags = 0): string
    {
        return json_encode($value, $flags | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
