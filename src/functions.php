<?php

declare(strict_types=1);

namespace NixPHP\CMS;

use NixPHP\CMS\Models\User;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Security\CspNonce;
use NixPHP\CMS\Support\Audit;
use NixPHP\CMS\Support\AuditEntry;
use NixPHP\CMS\Support\Cms;
use NixPHP\CMS\Support\UserHelper;
use function NixPHP\ORM\repo;
use function NixPHP\Session\session;

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
    static $cache = null;
    static $cachedUserId = null;
    static $forced = null;

    if ($reset) {
        $cache = null;
        $cachedUserId = null;
        $forced = null;
        return null;
    }

    if ($override !== null) {
        $forced = $override;
        return $forced;
    }

    if ($forced !== null) {
        return $forced;
    }

    if (!function_exists('NixPHP\Session\session')) {
        return null;
    }

    $userId = session()->get('userId');
    if ($userId === null) {
        $cache = null;
        $cachedUserId = null;
        return null;
    }

    if ($cache !== null && $cachedUserId === $userId) {
        return $cache;
    }

    /** @var User|null $user */
    $user = repo(UserRepository::class)->findOneBy('id', (int) $userId);
    if (!$user instanceof User) {
        $cache = null;
        $cachedUserId = null;
        return null;
    }

    $cachedUserId = $userId;
    $cache = new UserHelper($user);
    return $cache;
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
