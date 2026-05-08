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
use NixCMS\Content\Page\Block\Types\ArticleContentBlockType;
use NixCMS\Content\Page\Block\Types\ArticleMetaBlockType;
use NixCMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use NixCMS\Content\Page\Block\Types\ArticlesBlockType;
use NixCMS\Content\Page\Block\Types\ArticleTitleBlockType;
use NixCMS\Content\Page\Block\Types\AsideBlockType;
use NixCMS\Content\Page\Block\Types\ChartBlockType;
use NixCMS\Content\Page\Block\Types\ColumnsBlockType;
use NixCMS\Content\Page\Block\Types\ContactFormBlockType;
use NixCMS\Content\Page\Block\Types\CtaBlockType;
use NixCMS\Content\Page\Block\Types\EmbedBlockType;
use NixCMS\Content\Page\Block\Types\FeatureBlockType;
use NixCMS\Content\Page\Block\Types\FormBlockType;
use NixCMS\Content\Page\Block\Types\FooterBlockType;
use NixCMS\Content\Page\Block\Types\GridBlockType;
use NixCMS\Content\Page\Block\Types\HeaderBlockType;
use NixCMS\Content\Page\Block\Types\HeadingBlockType;
use NixCMS\Content\Page\Block\Types\HeroBlockType;
use NixCMS\Content\Page\Block\Types\ImageBlockType;
use NixCMS\Content\Page\Block\Types\MapBlockType;
use NixCMS\Content\Page\Block\Types\MediaBlockType;
use NixCMS\Content\Page\Block\Types\NavigationBlockType;
use NixCMS\Content\Page\Block\Types\QuoteBlockType;
use NixCMS\Content\Page\Block\Types\SectionBlockType;
use NixCMS\Content\Page\Block\Types\StackBlockType;
use NixCMS\Content\Page\Block\Types\StatsBlockType;
use NixCMS\Content\Page\Block\Types\TextBlockType;
use NixCMS\Mcp\Tools\AirQualityTool;
use NixCMS\Mcp\Tools\AdminNavigateTool;
use NixCMS\Mcp\Tools\AuditRecentTool;
use NixCMS\Mcp\Tools\ArticleDraftCreateTool;
use NixCMS\Mcp\Tools\ArticleDraftAppendTool;
use NixCMS\Mcp\Tools\ArticleLookupTool;
use NixCMS\Mcp\Tools\ArticleStatusCountsTool;
use NixCMS\Mcp\Tools\ContentOverviewTool;
use NixCMS\Mcp\Tools\ExchangeRatesTool;
use NixCMS\Mcp\Tools\MediaSummaryTool;
use NixCMS\Mcp\Tools\PageOverviewTool;
use NixCMS\Mcp\Tools\PageBuilderInspectTool;
use NixCMS\Mcp\Tools\PluginOverviewTool;
use NixCMS\Mcp\Tools\PublicHolidayTool;
use NixCMS\Mcp\Tools\QueueStatusTool;
use NixCMS\Mcp\Tools\SystemHealthTool;
use NixCMS\Mcp\Tools\ToolSearchTool;
use NixCMS\Mcp\Tools\TrendingTopicsTool;
use NixCMS\Mcp\Tools\UserRoleSummaryTool;
use NixCMS\Mcp\Tools\WeatherForecastTool;
use NixPHP\Core\Event;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;
use function NixCMS\cms;

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

cms()->blockTypes()->add(new ArticleContentBlockType());
cms()->blockTypes()->add(new ArticleMetaBlockType());
cms()->blockTypes()->add(new ArticleRelatedBlockType());
cms()->blockTypes()->add(new ArticlesBlockType());
cms()->blockTypes()->add(new ArticleTitleBlockType());
cms()->blockTypes()->add(new AsideBlockType());
cms()->blockTypes()->add(new ChartBlockType());
cms()->blockTypes()->add(new ColumnsBlockType());
cms()->blockTypes()->add(new ContactFormBlockType());
cms()->blockTypes()->add(new CtaBlockType());
cms()->blockTypes()->add(new EmbedBlockType());
cms()->blockTypes()->add(new FeatureBlockType());
cms()->blockTypes()->add(new FormBlockType());
cms()->blockTypes()->add(new FooterBlockType());
cms()->blockTypes()->add(new GridBlockType());
cms()->blockTypes()->add(new HeaderBlockType());
cms()->blockTypes()->add(new HeadingBlockType());
cms()->blockTypes()->add(new HeroBlockType());
cms()->blockTypes()->add(new ImageBlockType());
cms()->blockTypes()->add(new MapBlockType());
cms()->blockTypes()->add(new MediaBlockType());
cms()->blockTypes()->add(new NavigationBlockType());
cms()->blockTypes()->add(new QuoteBlockType());
cms()->blockTypes()->add(new SectionBlockType());
cms()->blockTypes()->add(new StackBlockType());
cms()->blockTypes()->add(new StatsBlockType());
cms()->blockTypes()->add(new TextBlockType());

if (app()->hasPlugin('nixphp/mcp')) {
    tool()->register(new SystemHealthTool());
    tool()->register(new AdminNavigateTool());
    tool()->register(new QueueStatusTool());
    tool()->register(new ContentOverviewTool());
    tool()->register(new ArticleStatusCountsTool());
    tool()->register(new ArticleDraftCreateTool());
    tool()->register(new ArticleDraftAppendTool());
    tool()->register(new ArticleLookupTool());
    tool()->register(new PageOverviewTool());
    tool()->register(new PageBuilderInspectTool());
    tool()->register(new ToolSearchTool());
    tool()->register(new MediaSummaryTool());
    tool()->register(new PluginOverviewTool());
    tool()->register(new UserRoleSummaryTool());
    tool()->register(new AuditRecentTool());
    tool()->register(new WeatherForecastTool());
    tool()->register(new PublicHolidayTool());
    tool()->register(new ExchangeRatesTool());
    tool()->register(new AirQualityTool());
    tool()->register(new TrendingTopicsTool());
}

if (!defined('PHPUNIT_RUNNING')) {
    app()->run();
}
