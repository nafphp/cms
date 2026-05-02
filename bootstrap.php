<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/vendor/autoload.php';

use NixCMS\Commands\CreateUserCommand;
use NixCMS\Commands\FakeDataCommand;
use NixCMS\Commands\InstallDatabaseCommand;
use NixCMS\Events\AdminGuardEvent;
use NixCMS\Events\AuthRedirectEvent;
use NixCMS\Events\SettingsLocaleEvent;
use NixCMS\Mcp\Tools\ArticleStatusCountsTool;
use NixCMS\Mcp\Tools\QueueStatusTool;
use NixCMS\Mcp\Tools\SystemHealthTool;
use NixCMS\Models\User;
use NixCMS\Support\Audit;
use NixCMS\Repositories\UserRepository;
use NixCMS\Support\UserHelper;
use NixPHP\Core\Event;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;
use function NixPHP\ORM\repo;
use function NixPHP\Session\session;

const BASE_PATH = __DIR__;

event()->listen(Event::EXCEPTION, [AuthRedirectEvent::class, 'onException'], -100);
event()->listen(Event::REQUEST_START, [SettingsLocaleEvent::class, 'handle'], -10);
event()->listen(Event::CONTROLLER_CALLING, [AdminGuardEvent::class, 'handle']);

command()->add(InstallDatabaseCommand::class);
command()->add(CreateUserCommand::class);
command()->add(FakeDataCommand::class);

if (app()->hasPlugin('nixphp/mcp')) {
    tool()->register(new SystemHealthTool());
    tool()->register(new QueueStatusTool());
    tool()->register(new ArticleStatusCountsTool());
}

function cms(): \NixCMS\Support\Cms
{
    static $cache = null;

    if ($cache === null) {
        $cache = new \NixCMS\Support\Cms();
    }

    return $cache;
}

function settings(): \NixCMS\Settings\SettingsRegistry
{
    return cms()->settingsRegistry();
}

function user(): ?UserHelper
{
    static $cache = null;
    static $cachedUserId = null;

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

/**
 * @param array<string, mixed> $meta
 */
function audit(
    string $action,
    string $subjectType,
    string $subjectKey,
    mixed $oldValue = null,
    mixed $newValue = null,
    array $meta = [],
): bool {
    return (new Audit())->record($action, $subjectType, $subjectKey, $oldValue, $newValue, $meta);
}

if (!defined('PHPUNIT_RUNNING')) {
    app()->run();
}
