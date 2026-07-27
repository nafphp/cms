<?php

declare(strict_types=1);

namespace NixPHP\CMS\Security;

/**
 * Central allowlist for URLs rendered from stored CMS content.
 */
final class UrlPolicy
{
    public static function internal(string $value): ?string
    {
        $value = self::normalize($value);
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, '#')) {
            return strlen($value) > 1 ? $value : '#';
        }

        if (!str_starts_with($value, '/') || str_starts_with($value, '//') || str_starts_with($value, '/\\')) {
            return null;
        }

        return $value;
    }

    public static function external(string $value, bool $allowMailto = false, bool $allowTel = false): ?string
    {
        $value = self::normalize($value);
        if ($value === null || str_starts_with($value, '//')) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            $host = parse_url($value, PHP_URL_HOST);
            return is_string($host) && $host !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false
                ? $value
                : null;
        }

        if ($allowMailto && $scheme === 'mailto') {
            $address = substr($value, strlen('mailto:'));
            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
        }

        if ($allowTel && $scheme === 'tel') {
            $number = substr($value, strlen('tel:'));
            return preg_match('/^\+?[0-9(). -]{3,40}$/', $number) === 1 ? $value : null;
        }

        return null;
    }

    public static function link(string $value, bool $allowMailto = true, bool $allowTel = true): ?string
    {
        return self::internal($value) ?? self::external($value, $allowMailto, $allowTel);
    }

    public static function media(string $value): ?string
    {
        return self::internal($value) ?? self::external($value);
    }

    public static function embed(string $value): ?string
    {
        return self::external($value);
    }

    private static function normalize(string $value): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            return null;
        }

        return $value;
    }
}
