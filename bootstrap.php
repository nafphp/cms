<?php

declare(strict_types=1);

use src\Commands\CheckAssetsCommand;
use src\Commands\CreateUserCommand;
use src\Commands\DebugAiToolRoutingCommand;
use src\Commands\DocsCheckCommand;
use src\Commands\DocsSyncCommand;
use src\Commands\FakeDataCommand;
use src\Commands\InstallAgentGuideCommand;
use src\Commands\InstallDatabaseCommand;
use src\Commands\IntegrityCheckCommand;
use src\Commands\PublishAssetsCommand;
use src\Commands\RemoveAssetsCommand;
use src\Content\Page\Block\Types\ArticleContentBlockType;
use src\Content\Page\Block\Types\ArticleMetaBlockType;
use src\Content\Page\Block\Types\ArticleRelatedBlockType;
use src\Content\Page\Block\Types\ArticlesBlockType;
use src\Content\Page\Block\Types\ArticleTitleBlockType;
use src\Content\Page\Block\Types\AsideBlockType;
use src\Content\Page\Block\Types\ChartBlockType;
use src\Content\Page\Block\Types\ColumnsBlockType;
use src\Content\Page\Block\Types\ContactFormBlockType;
use src\Content\Page\Block\Types\CtaBlockType;
use src\Content\Page\Block\Types\EmbedBlockType;
use src\Content\Page\Block\Types\FeatureBlockType;
use src\Content\Page\Block\Types\FooterBlockType;
use src\Content\Page\Block\Types\FormBlockType;
use src\Content\Page\Block\Types\GridBlockType;
use src\Content\Page\Block\Types\HeaderBlockType;
use src\Content\Page\Block\Types\HeadingBlockType;
use src\Content\Page\Block\Types\HeroBlockType;
use src\Content\Page\Block\Types\ImageBlockType;
use src\Content\Page\Block\Types\MapBlockType;
use src\Content\Page\Block\Types\MediaBlockType;
use src\Content\Page\Block\Types\NavigationBlockType;
use src\Content\Page\Block\Types\QuoteBlockType;
use src\Content\Page\Block\Types\SectionBlockType;
use src\Content\Page\Block\Types\StackBlockType;
use src\Content\Page\Block\Types\StatsBlockType;
use src\Content\Page\Block\Types\TextBlockType;
use src\Events\AdminGuardEvent;
use src\Events\AuthRedirectEvent;
use src\Events\FormPayloadLimitEvent;
use src\Events\SecurityHeadersEvent;
use src\Events\SettingsLocaleEvent;
use src\Localization\RuntimeTranslator;
use src\Mcp\Tools\AdminNavigateTool;
use src\Mcp\Tools\AirQualityTool;
use src\Mcp\Tools\ArticleDraftAppendTool;
use src\Mcp\Tools\ArticleDraftCreateTool;
use src\Mcp\Tools\ArticleLookupTool;
use src\Mcp\Tools\ArticleStatusCountsTool;
use src\Mcp\Tools\AuditRecentTool;
use src\Mcp\Tools\ContentOverviewTool;
use src\Mcp\Tools\ExchangeRatesTool;
use src\Mcp\Tools\MediaSummaryTool;
use src\Mcp\Tools\PageBuilderInspectTool;
use src\Mcp\Tools\PageOverviewTool;
use src\Mcp\Tools\PluginOverviewTool;
use src\Mcp\Tools\PublicHolidayTool;
use src\Mcp\Tools\QueueStatusTool;
use src\Mcp\Tools\SystemHealthTool;
use src\Mcp\Tools\ToolSearchTool;
use src\Mcp\Tools\TrendingTopicsTool;
use src\Mcp\Tools\UserRoleSummaryTool;
use src\Mcp\Tools\WeatherForecastTool;
use NixPHP\Core\Event;
use NixPHP\Database\Support\MigrationRegistry;
use NixPHP\I18n\Core\Translator;
use function NixCMS\cms;
use function NixPHP\app;
use function NixPHP\CLI\command;
use function NixPHP\event;
use function NixPHP\MCP\tool;

$packageRoot = __DIR__;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

app()->container()->set(Translator::class, static fn() => new RuntimeTranslator());
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
