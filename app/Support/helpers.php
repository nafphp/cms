<?php

declare(strict_types=1);

use NixCMS\Repositories\UserRepository;
use NixCMS\Settings\SettingsRegistry;
use NixCMS\Support\Audit;
use NixCMS\Support\AuditEntry;
use NixCMS\Support\Cms;
use NixCMS\Support\UserHelper;
use NixCMS\Models\User;
use function NixPHP\ORM\repo;

if (!function_exists('cms')) {
    function cms(): Cms
    {
        static $cache = null;

        if ($cache === null) {
            $cache = new Cms();
        }

        return $cache;
    }
}

if (!function_exists('settings')) {
    function settings(): SettingsRegistry
    {
        return cms()->settingsRegistry();
    }
}

function user(): ?UserHelper
{
    static $cache = null;
    static $cachedUserId = null;

    if (!function_exists('NixPHP\Session\session')) {
        return null;
    }

    $userId = \NixPHP\Session\session()->get('userId');
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

if (!function_exists('audit')) {
    function audit(): Audit
    {
        static $cache = null;

        if ($cache === null) {
            $cache = new Audit();
        }

        return $cache;
    }
}

if (!function_exists('audit_log')) {
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
}
