<?php

declare(strict_types=1);

namespace Naf\CMS;

use Naf\CMS\Security\CspNonce;
use Naf\CMS\Services\Security\UserIdentity;
use Naf\CMS\Support\Audit;
use Naf\CMS\Support\AuditEntry;
use Naf\CMS\Support\Cms;
use Naf\CMS\Support\UserHelper;

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
        \Naf\Auth\auth()->reset();

        return null;
    }
    if ($override !== null) {
        return $forced = $override;
    }
    if ($forced !== null) {
        return $forced;
    }

    $identity = \Naf\Auth\auth()->user();
    if (!$identity instanceof UserIdentity || $identity->user === null) {
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
