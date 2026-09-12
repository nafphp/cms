<?php

declare(strict_types=1);

namespace NixPHP\CMS;

use NixPHP\CMS\Security\CspNonce;
use NixPHP\CMS\Support\Audit;
use NixPHP\CMS\Support\AuditEntry;
use NixPHP\CMS\Support\Cms;
use NixPHP\CMS\Support\UserHelper;

/**
 * Reset request-scoped helper state between tests.
 */
function cms_reset(): void
{
    cms(reset: true);
    user(reset: true);
    audit(reset: true);
    CspNonce::reset();
}

function csp_nonce(): string
{
    return CspNonce::value();
}

function cms(bool $reset = false, ?Cms $override = null): Cms
{
    static $cache = null;

    if ($override instanceof Cms) {
        return $cache = $override;
    }

    if ($reset) {
        $cache = null;
    }

    if ($cache === null) {
        $cache = new Cms();
    }

    return $cache;
}

/**
 * The override exists for isolated tests only; production callers must rely on
 * the authenticated session.
 */
function user(?UserHelper $override = null, bool $reset = false): ?UserHelper
{
    static $forced = null;
    if ($reset) {
        $forced = null;
        \NixPHP\Auth\auth()->reset();
        return null;
    }
    if ($override !== null) { return $forced = $override; }
    if ($forced !== null) { return $forced; }

    $identity = \NixPHP\Auth\auth()->user();
    if (!$identity instanceof \NixPHP\CMS\Services\Security\UserIdentity || $identity->user === null) {
        return null;
    }
    return new UserHelper($identity->user);
}

function audit(bool $reset = false): Audit
{
    static $cache = null;

    if ($reset) {
        $cache = null;
    }

    if ($cache === null) {
        $cache = new Audit();
    }

    return $cache;
}

/**
 * @param array<string, mixed> $meta
 */
function audit_log(
    AuditEntry|string $action,
    ?string $subjectType = null,
    ?string $subjectKey = null,
    mixed $oldValue = null,
    mixed $newValue = null,
    array $meta = [],
): bool {
    return audit()->record($action, $subjectType, $subjectKey, $oldValue, $newValue, $meta);
}
