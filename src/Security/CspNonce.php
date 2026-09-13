<?php

declare(strict_types=1);

namespace Naf\CMS\Security;

/**
 * Per-request nonce shared by the CSP header and rendered script tags.
 */
final class CspNonce
{
    private static ?string $nonce = null;

    public static function value(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    /** Reset process state between simulated requests in tests. */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
