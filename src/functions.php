<?php

declare(strict_types=1);

namespace NixPHP\CMS;

use NixPHP\CMS\Models\User;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Support\Audit;
use NixPHP\CMS\Support\AuditEntry;
use NixPHP\CMS\Support\Cms;
use NixPHP\CMS\Support\UserHelper;
use function NixPHP\ORM\repo;
use function NixPHP\Session\session;

function cms(): Cms
{
    static $cache = null;

    if ($cache === null) {
        $cache = new Cms();
    }

    return $cache;
}

function user(): ?UserHelper
{
    static $cache = null;
    static $cachedUserId = null;

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

function audit(): Audit
{
    static $cache = null;

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
