<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/vendor/autoload.php';

use Cms\Commands\CreateDatabaseCommand;
use Cms\Commands\CreateUserCommand;
use Cms\Commands\FakeDataCommand;
use Cms\Commands\InstallDatabaseCommand;
use Cms\Enums\Permission;
use Cms\Enums\Role;
use Cms\Events\AdminGuardEvent;
use Cms\Events\AuthRedirectEvent;
use Cms\Mcp\Tools\ArticleStatusCountsTool;
use Cms\Mcp\Tools\QueueStatusTool;
use Cms\Mcp\Tools\SystemHealthTool;
use Cms\Models\User;
use Cms\Repositories\UserRepository;
use Cms\Support\UserHelper;
use NixPHP\Core\Event;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;
use function NixPHP\ORM\repo;
use function NixPHP\Session\session;

const BASE_PATH = __DIR__;

event()->listen(Event::EXCEPTION, [AuthRedirectEvent::class, 'onException'], -100);
event()->listen(Event::CONTROLLER_CALLING, [AdminGuardEvent::class, 'handle']);

command()->add(CreateDatabaseCommand::class);
command()->add(InstallDatabaseCommand::class);
command()->add(CreateUserCommand::class);
command()->add(FakeDataCommand::class);

if (app()->hasPlugin('nixphp/mcp')) {
    tool()->register(new SystemHealthTool());
    tool()->register(new QueueStatusTool());
    tool()->register(new ArticleStatusCountsTool());
}

function cms(): \Cms\Cms
{
    static $cache = null;

    if ($cache === null) {
        $cache = new \Cms\Cms();
    }

    return $cache;
}

function user(): ?UserHelper
{
    static $cache = null;
    static $cachedUserId = null;

    if (session()->get('dummyAdmin') === true) {
        if ($cache !== null && $cachedUserId === 'dummy-admin') {
            return $cache;
        }

        $cachedUserId = 'dummy-admin';
        $cache = new UserHelper(
            null,
            Permission::cases(),
            [Role::ADMIN],
            true,
        );
        return $cache;
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

if (!defined('PHPUNIT_RUNNING')) {
    app()->run();
}
