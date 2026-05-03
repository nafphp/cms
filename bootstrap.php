<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/vendor/autoload.php';

use NixCMS\Commands\CreateUserCommand;
use NixCMS\Commands\DocsCheckCommand;
use NixCMS\Commands\DocsSyncCommand;
use NixCMS\Commands\FakeDataCommand;
use NixCMS\Commands\InstallDatabaseCommand;
use NixCMS\Commands\IntegrityCheckCommand;
use NixCMS\Events\AdminGuardEvent;
use NixCMS\Events\AuthRedirectEvent;
use NixCMS\Events\SettingsLocaleEvent;
use NixCMS\Mcp\Tools\ArticleDraftCreateTool;
use NixCMS\Mcp\Tools\ArticleDraftAppendTool;
use NixCMS\Mcp\Tools\ArticleLookupTool;
use NixCMS\Mcp\Tools\ArticleStatusCountsTool;
use NixCMS\Mcp\Tools\MediaSummaryTool;
use NixCMS\Mcp\Tools\PluginOverviewTool;
use NixCMS\Mcp\Tools\QueueStatusTool;
use NixCMS\Mcp\Tools\SystemHealthTool;
use NixCMS\Mcp\Tools\UserRoleSummaryTool;
use NixPHP\Core\Event;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;

const BASE_PATH = __DIR__;

event()->listen(Event::EXCEPTION, [AuthRedirectEvent::class, 'onException'], -100);
event()->listen(Event::REQUEST_START, [SettingsLocaleEvent::class, 'handle'], -10);
event()->listen(Event::CONTROLLER_CALLING, [AdminGuardEvent::class, 'handle']);

command()->add(InstallDatabaseCommand::class);
command()->add(CreateUserCommand::class);
command()->add(FakeDataCommand::class);
command()->add(DocsSyncCommand::class);
command()->add(DocsCheckCommand::class);
command()->add(IntegrityCheckCommand::class);

cms()->blockTypes()->addMany(require __DIR__ . '/app/blockTypes.php');

if (app()->hasPlugin('nixphp/mcp')) {
    tool()->register(new SystemHealthTool());
    tool()->register(new QueueStatusTool());
    tool()->register(new ArticleStatusCountsTool());
    tool()->register(new ArticleDraftCreateTool());
    tool()->register(new ArticleDraftAppendTool());
    tool()->register(new ArticleLookupTool());
    tool()->register(new MediaSummaryTool());
    tool()->register(new PluginOverviewTool());
    tool()->register(new UserRoleSummaryTool());
}

if (!defined('PHPUNIT_RUNNING')) {
    app()->run();
}
