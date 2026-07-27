<?php

declare(strict_types=1);

use NixPHP\CMS\Commands\CheckAssetsCommand;
use NixPHP\CMS\Commands\CreateUserCommand;
use NixPHP\CMS\Commands\DebugAiToolRoutingCommand;
use NixPHP\CMS\Commands\DocsCheckCommand;
use NixPHP\CMS\Commands\DocsSyncCommand;
use NixPHP\CMS\Commands\FakeDataCommand;
use NixPHP\CMS\Commands\InstallAgentGuideCommand;
use NixPHP\CMS\Commands\InstallDatabaseCommand;
use NixPHP\CMS\Commands\IntegrityCheckCommand;
use NixPHP\CMS\Commands\PublishAssetsCommand;
use NixPHP\CMS\Commands\RemoveAssetsCommand;
use NixPHP\CMS\Content\Page\Block\Types\ArticleContentBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleMetaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticlesBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleTitleBlockType;
use NixPHP\CMS\Content\Page\Block\Types\AsideBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ChartBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ColumnsBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ContactFormBlockType;
use NixPHP\CMS\Content\Page\Block\Types\CtaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\EmbedBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FeatureBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FooterBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FormBlockType;
use NixPHP\CMS\Content\Page\Block\Types\GridBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeaderBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeadingBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeroBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ImageBlockType;
use NixPHP\CMS\Content\Page\Block\Types\MapBlockType;
use NixPHP\CMS\Content\Page\Block\Types\MediaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\NavigationBlockType;
use NixPHP\CMS\Content\Page\Block\Types\QuoteBlockType;
use NixPHP\CMS\Content\Page\Block\Types\SectionBlockType;
use NixPHP\CMS\Content\Page\Block\Types\StackBlockType;
use NixPHP\CMS\Content\Page\Block\Types\StatsBlockType;
use NixPHP\CMS\Content\Page\Block\Types\TextBlockType;
use NixPHP\CMS\Events\AdminGuardEvent;
use NixPHP\CMS\Events\AuthRedirectEvent;
use NixPHP\CMS\Events\FormPayloadLimitEvent;
use NixPHP\CMS\Events\SecurityHeadersEvent;
use NixPHP\CMS\Events\SettingsLocaleEvent;
use NixPHP\CMS\Localization\RuntimeTranslator;
use NixPHP\CMS\Mcp\Tools\AdminNavigateTool;
use NixPHP\CMS\Mcp\Tools\AirQualityTool;
use NixPHP\CMS\Mcp\Tools\ArticleDraftAppendTool;
use NixPHP\CMS\Mcp\Tools\ArticleDraftCreateTool;
use NixPHP\CMS\Mcp\Tools\ArticleLookupTool;
use NixPHP\CMS\Mcp\Tools\ArticleStatusCountsTool;
use NixPHP\CMS\Mcp\Tools\AuditRecentTool;
use NixPHP\CMS\Mcp\Tools\ContentOverviewTool;
use NixPHP\CMS\Mcp\Tools\ExchangeRatesTool;
use NixPHP\CMS\Mcp\Tools\MediaSummaryTool;
use NixPHP\CMS\Mcp\Tools\PageBuilderInspectTool;
use NixPHP\CMS\Mcp\Tools\PageOverviewTool;
use NixPHP\CMS\Mcp\Tools\PluginOverviewTool;
use NixPHP\CMS\Mcp\Tools\PublicHolidayTool;
use NixPHP\CMS\Mcp\Tools\QueueStatusTool;
use NixPHP\CMS\Mcp\Tools\SystemHealthTool;
use NixPHP\CMS\Mcp\Tools\ToolSearchTool;
use NixPHP\CMS\Mcp\Tools\TrendingTopicsTool;
use NixPHP\CMS\Mcp\Tools\UserRoleSummaryTool;
use NixPHP\CMS\Mcp\Tools\WeatherForecastTool;
use NixPHP\Core\Event;
use NixPHP\Database\Support\MigrationRegistry;
use NixPHP\I18n\Core\Translator;
use NixPHP\MCP\Support\ToolRegistry;
use function NixPHP\CMS\cms;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;

$packageRoot = __DIR__;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

app()->container()->set(Translator::class, static fn() => new RuntimeTranslator());
app()->container()->set(ToolRegistry::class, static fn() => new ToolRegistry());


MigrationRegistry::addPath($packageRoot . '/src/Migrations');

event()->listen(Event::EXCEPTION, [AuthRedirectEvent::class, 'onException'], -100);
event()->listen(Event::REQUEST_START, [SettingsLocaleEvent::class, 'handle'], -10);
event()->listen(Event::REQUEST_START, [FormPayloadLimitEvent::class, 'handle'], 100);
event()->listen(Event::CONTROLLER_CALLING, [AdminGuardEvent::class, 'handle']);
event()->listen(Event::RESPONSE_HEADER, [SecurityHeadersEvent::class, 'handle']);

command()->add(InstallDatabaseCommand::class);
command()->add(InstallAgentGuideCommand::class);
command()->add(CreateUserCommand::class);
command()->add(FakeDataCommand::class);
command()->add(DebugAiToolRoutingCommand::class);
command()->add(PublishAssetsCommand::class);
command()->add(CheckAssetsCommand::class);
command()->add(RemoveAssetsCommand::class);

if (realpath((string) app()->getBasePath()) === realpath($packageRoot)) {
    command()->add(DocsSyncCommand::class);
    command()->add(DocsCheckCommand::class);
    command()->add(IntegrityCheckCommand::class);
}

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
cms()->blockTypes()->add(new FooterBlockType());
cms()->blockTypes()->add(new FormBlockType());
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
