<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

use Naf\Auth\Session\StateStoreInterface;
use Naf\CMS\Commands\CheckAssetsCommand;
use Naf\CMS\Commands\CreateUserCommand;
use Naf\CMS\Commands\DebugAiToolRoutingCommand;
use Naf\CMS\Commands\DocsCheckCommand;
use Naf\CMS\Commands\DocsSyncCommand;
use Naf\CMS\Commands\FakeDataCommand;
use Naf\CMS\Commands\InstallAgentGuideCommand;
use Naf\CMS\Commands\InstallDatabaseCommand;
use Naf\CMS\Commands\IntegrityCheckCommand;
use Naf\CMS\Commands\MakeBlockCommand;
use Naf\CMS\Commands\MakePluginCommand;
use Naf\CMS\Commands\MakeTemplateCommand;
use Naf\CMS\Commands\PageBuilderAuditCommand;
use Naf\CMS\Commands\PublishAssetsCommand;
use Naf\CMS\Commands\RemoveAssetsCommand;
use Naf\CMS\Content\Page\Block\Types\ArticleContentBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleMetaBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticlesBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleTitleBlockType;
use Naf\CMS\Content\Page\Block\Types\AsideBlockType;
use Naf\CMS\Content\Page\Block\Types\ChartBlockType;
use Naf\CMS\Content\Page\Block\Types\ColumnsBlockType;
use Naf\CMS\Content\Page\Block\Types\ContactFormBlockType;
use Naf\CMS\Content\Page\Block\Types\CtaBlockType;
use Naf\CMS\Content\Page\Block\Types\EmbedBlockType;
use Naf\CMS\Content\Page\Block\Types\FeatureBlockType;
use Naf\CMS\Content\Page\Block\Types\FooterBlockType;
use Naf\CMS\Content\Page\Block\Types\FormBlockType;
use Naf\CMS\Content\Page\Block\Types\GridBlockType;
use Naf\CMS\Content\Page\Block\Types\HeaderBlockType;
use Naf\CMS\Content\Page\Block\Types\HeadingBlockType;
use Naf\CMS\Content\Page\Block\Types\HeroBlockType;
use Naf\CMS\Content\Page\Block\Types\ImageBlockType;
use Naf\CMS\Content\Page\Block\Types\MapBlockType;
use Naf\CMS\Content\Page\Block\Types\MediaBlockType;
use Naf\CMS\Content\Page\Block\Types\NavigationBlockType;
use Naf\CMS\Content\Page\Block\Types\QuoteBlockType;
use Naf\CMS\Content\Page\Block\Types\SectionBlockType;
use Naf\CMS\Content\Page\Block\Types\StackBlockType;
use Naf\CMS\Content\Page\Block\Types\StatsBlockType;
use Naf\CMS\Content\Page\Block\Types\TextBlockType;
use Naf\CMS\Contracts\AuditSinkInterface;
use Naf\CMS\Contracts\MediaStorageInterface;
use Naf\CMS\Contracts\OutboundHttpClientInterface;
use Naf\CMS\Contracts\PasswordPolicyInterface;
use Naf\CMS\Contracts\RateLimiterInterface;
use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Contracts\ThemeSourceInterface;
use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Contracts\UserPrivilegePolicyInterface;
use Naf\CMS\Events\AdminGuardEvent;
use Naf\CMS\Events\AuthRedirectEvent;
use Naf\CMS\Events\FormPayloadLimitEvent;
use Naf\CMS\Events\SecurityHeadersEvent;
use Naf\CMS\Events\SettingsLocaleEvent;
use Naf\CMS\Localization\RuntimeTranslator;
use Naf\CMS\Mcp\Tools\AdminNavigateTool;
use Naf\CMS\Mcp\Tools\AirQualityTool;
use Naf\CMS\Mcp\Tools\ArticleDraftAppendTool;
use Naf\CMS\Mcp\Tools\ArticleDraftCreateTool;
use Naf\CMS\Mcp\Tools\ArticleLookupTool;
use Naf\CMS\Mcp\Tools\ArticleStatusCountsTool;
use Naf\CMS\Mcp\Tools\AuditRecentTool;
use Naf\CMS\Mcp\Tools\ContentOverviewTool;
use Naf\CMS\Mcp\Tools\ExchangeRatesTool;
use Naf\CMS\Mcp\Tools\MediaSummaryTool;
use Naf\CMS\Mcp\Tools\PageBuilderInspectTool;
use Naf\CMS\Mcp\Tools\PageOverviewTool;
use Naf\CMS\Mcp\Tools\PluginOverviewTool;
use Naf\CMS\Mcp\Tools\PublicHolidayTool;
use Naf\CMS\Mcp\Tools\QueueStatusTool;
use Naf\CMS\Mcp\Tools\SystemHealthTool;
use Naf\CMS\Mcp\Tools\ToolSearchTool;
use Naf\CMS\Mcp\Tools\TrendingTopicsTool;
use Naf\CMS\Mcp\Tools\UserRoleSummaryTool;
use Naf\CMS\Mcp\Tools\WeatherForecastTool;
use Naf\CMS\Models\Media;
use Naf\CMS\Repositories\AuditLogRepository;
use Naf\CMS\Repositories\ThemeRepository;
use Naf\CMS\Services\Http\OutboundHttpClient;
use Naf\CMS\Services\Media\MediaStorageService;
use Naf\CMS\Services\Security\CmsSessionStateStore;
use Naf\CMS\Services\Security\DatabaseProvider;
use Naf\CMS\Services\Security\LoginRateLimiter;
use Naf\CMS\Services\Security\PasswordPolicy;
use Naf\CMS\Services\Security\UserPrivilegePolicy;
use Naf\CMS\Services\Settings\SettingsService;
use Naf\CMS\Services\Settings\TranslationFileService;
use Naf\CMS\Storage\EntityStorage;
use Naf\Core\Event;
use Naf\Database\Support\MigrationRegistry;
use Naf\I18n\Core\Translator;

use function Naf\app;
use function Naf\CLI\command;
use function Naf\CMS\cms;
use function Naf\event;
use function Naf\MCP\tool;

$packageRoot = __DIR__;

// Boot the dependency before registration even when a host lists CMS first.
app()->getPlugin('naf/auth')->boot();
app()->container()->set(CmsSessionStateStore::class, static fn()
    => new CmsSessionStateStore(\Naf\Session\session()));
app()->container()->set(StateStoreInterface::class, static fn()
    => app()->container()->get(CmsSessionStateStore::class));
app()->container()->set(DatabaseProvider::class, static fn()
    => app()->container()->make(DatabaseProvider::class));
\Naf\Auth\auth()->addProvider('database', DatabaseProvider::class);

app()->container()->set(Translator::class, static fn() => new RuntimeTranslator());
app()->container()->set(ThemeSourceInterface::class, static fn() => new ThemeRepository());
app()->container()->set(MediaStorageInterface::class, static fn() => new MediaStorageService(new EntityStorage(Media::class)));
app()->container()->set(SettingsServiceInterface::class, static fn() => new SettingsService());
app()->container()->set(TranslationServiceInterface::class, static fn() => new TranslationFileService());
app()->container()->set(RateLimiterInterface::class, static fn() => new LoginRateLimiter());
app()->container()->set(AuditSinkInterface::class, static fn() => new AuditLogRepository());
app()->container()->set(OutboundHttpClientInterface::class, static fn() => new OutboundHttpClient());
app()->container()->set(PasswordPolicyInterface::class, static fn() => new PasswordPolicy());
app()->container()->set(UserPrivilegePolicyInterface::class, static fn() => new UserPrivilegePolicy());


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
command()->add(IntegrityCheckCommand::class);
command()->add(MakeBlockCommand::class);
command()->add(MakeTemplateCommand::class);
command()->add(MakePluginCommand::class);
command()->add(PageBuilderAuditCommand::class);

if (realpath((string) app()->getBasePath()) === realpath($packageRoot)) {
    command()->add(DocsSyncCommand::class);
    command()->add(DocsCheckCommand::class);

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
