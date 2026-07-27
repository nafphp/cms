<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use src\Enums\Permission;
use src\Navigation\NavigationItem;
use src\Api\NavigationApi;
use src\Api\ScopedNavigationApi;
use src\Enums\Scope;
use src\Repositories\NavigationRepository;
use src\Page\Page;
use src\Page\PageRegistry;
use src\Services\Admin\AdminNavigationService;
use src\Support\UserHelper;
use PHPUnit\Framework\TestCase;

final class AdminNavigationServiceTest extends TestCase
{
    public function testServiceBuildsNestedNavigationFromRegistryAndPages(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->addMany([
            NavigationItem::create('content')->setLabel('Content')->setOrder(20)->setScope('admin'),
            NavigationItem::create('content.reports')->setLabel('Reports')->setParent('content')->setOrder(20)->setScope('admin'),
        ]);

        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('admin.dashboard')
                ->setPath('/admin')
                ->setAdminNavigation('Dashboard', order: 10, key: 'dashboard'),
            Page::create('admin.articles')
                ->setPath('/admin/articles')
                ->setAdminNavigation('Articles', parent: 'content', order: 10, key: 'articles'),
            Page::create('admin.articles.edit')
                ->setPath('/admin/articles/{articleId}/edit')
                ->hideFromAdminNavigation('articles'),
            Page::create('admin.reports')
                ->setPath('/admin/reports')
                ->setAdminNavigation('Monthly', parent: 'content.reports', order: 10, key: 'content.reports.monthly'),
        ]);

        $items = (new AdminNavigationService($navigation, $pages, includeConfiguredItems: false))->items();

        self::assertSame('dashboard', $items[0]['key']);
        self::assertSame('content', $items[1]['key']);
        self::assertSame('articles', $items[1]['children'][0]['key']);
        self::assertSame(['admin.articles', 'admin.articles.edit'], $items[1]['children'][0]['routeNames']);
        self::assertSame('content.reports', $items[1]['children'][1]['key']);
        self::assertSame('content.reports.monthly', $items[1]['children'][1]['children'][0]['key']);
        self::assertSame(['admin.articles', 'admin.articles.edit', 'admin.reports'], $items[1]['routeNames']);
    }

    public function testServiceCreatesMissingParentPlaceholdersForPluginChildren(): void
    {
        $pages = new PageRegistry();
        $pages->add(
            Page::create('admin.plugin')
                ->setPath('/admin/plugin')
                ->setAdminNavigation('Plugin Home', parent: 'plugins.analytics', key: 'plugins.analytics.home')
        );

        $items = (new AdminNavigationService(new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN), $pages, includeConfiguredItems: false))->items();

        self::assertSame('plugins', $items[0]['key']);
        self::assertSame('Analytics', $items[0]['children'][0]['label']);
        self::assertSame('plugins.analytics.home', $items[0]['children'][0]['children'][0]['key']);
    }

    public function testServiceIgnoresFrontendNavigationItems(): void
    {
        $baseNavigation = new NavigationApi(new NavigationRepository(), false);
        $baseNavigation->add(NavigationItem::create('frontend.docs')->setLabel('Docs'));
        $navigation = new ScopedNavigationApi($baseNavigation, Scope::ADMIN);
        $navigation->add(NavigationItem::create('admin.tools')->setLabel('Tools'));

        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('home')
                ->setPath('/')
                ->setNavigation('Home', key: 'home'),
            Page::create('admin.tools')
                ->setPath('/admin/tools')
                ->setAdminNavigation('Tools', key: 'admin.tools'),
        ]);

        $items = (new AdminNavigationService($navigation, $pages, includeConfiguredItems: false))->items();

        self::assertCount(1, $items);
        self::assertSame('admin.tools', $items[0]['key']);
        self::assertSame(['admin.tools'], $items[0]['routeNames']);
    }

    public function testServiceBuildsArbitrarilyDeepTreeAndPropagatesDescendantRoutes(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->addMany([
            NavigationItem::create('plugins')
                ->setLabel('Plugins')
                ->setOrder(50)
                ->setScope('admin'),
            NavigationItem::create('plugins.analytics')
                ->setLabel('Analytics')
                ->setParent('plugins')
                ->setOrder(10)
                ->setScope('admin'),
            NavigationItem::create('plugins.analytics.reports')
                ->setLabel('Reports')
                ->setParent('plugins.analytics')
                ->setOrder(20)
                ->setScope('admin'),
        ]);

        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('admin.analytics.daily')
                ->setPath('/admin/plugins/analytics/reports/daily')
                ->setAdminNavigation('Daily', parent: 'plugins.analytics.reports', order: 10, key: 'plugins.analytics.reports.daily'),
            Page::create('admin.analytics.daily.detail')
                ->setPath('/admin/plugins/analytics/reports/daily/{id}')
                ->hideFromAdminNavigation('plugins.analytics.reports.daily'),
            Page::create('admin.analytics.monthly')
                ->setPath('/admin/plugins/analytics/reports/monthly')
                ->setAdminNavigation('Monthly', parent: 'plugins.analytics.reports', order: 20, key: 'plugins.analytics.reports.monthly'),
        ]);

        $tree = (new AdminNavigationService($navigation, $pages, includeConfiguredItems: false))->items();
        $plugins = $this->findNode($tree, 'plugins');
        $reports = $this->findNode($tree, 'plugins.analytics.reports');
        $daily = $this->findNode($tree, 'plugins.analytics.reports.daily');

        self::assertNotNull($plugins);
        self::assertNotNull($reports);
        self::assertNotNull($daily);
        self::assertSame('plugins.analytics', $plugins['children'][0]['key']);
        self::assertSame('plugins.analytics.reports', $plugins['children'][0]['children'][0]['key']);
        self::assertSame('plugins.analytics.reports.daily', $reports['children'][0]['key']);
        self::assertSame('plugins.analytics.reports.monthly', $reports['children'][1]['key']);
        self::assertSame([
            'admin.analytics.daily',
            'admin.analytics.daily.detail',
            'admin.analytics.monthly',
        ], $reports['routeNames']);
        self::assertSame([
            'admin.analytics.daily',
            'admin.analytics.daily.detail',
        ], $daily['routeNames']);
        self::assertSame($reports['routeNames'], $plugins['routeNames']);
    }

    public function testServiceSortsSiblingsByOrderThenLabel(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->addMany([
            NavigationItem::create('tools.zebra')->setLabel('Zebra')->setParent('tools')->setOrder(20)->setScope('admin'),
            NavigationItem::create('tools.alpha')->setLabel('Alpha')->setParent('tools')->setOrder(20)->setScope('admin'),
            NavigationItem::create('tools.first')->setLabel('First')->setParent('tools')->setOrder(10)->setScope('admin'),
            NavigationItem::create('tools')->setLabel('Tools')->setOrder(10)->setScope('admin'),
        ]);

        $items = (new AdminNavigationService($navigation, new PageRegistry(), includeConfiguredItems: false))->items();

        self::assertSame('tools', $items[0]['key']);
        self::assertSame([
            'tools.first',
            'tools.alpha',
            'tools.zebra',
        ], array_column($items[0]['children'], 'key'));
    }

    public function testServiceKeepsAdminAndFrontendPlaceholderBranchesSeparated(): void
    {
        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('docs.install')
                ->setPath('/docs/install')
                ->setNavigation('Install', parent: 'docs.guides', key: 'docs.guides.install'),
            Page::create('admin.docs')
                ->setPath('/admin/docs')
                ->setAdminNavigation('Admin Docs', parent: 'docs.admin', key: 'docs.admin.index'),
        ]);

        $items = (new AdminNavigationService(new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN), $pages, includeConfiguredItems: false))->items();

        self::assertCount(1, $items);
        self::assertSame('docs', $items[0]['key']);
        self::assertSame('docs.admin', $items[0]['children'][0]['key']);
        self::assertSame('docs.admin.index', $items[0]['children'][0]['children'][0]['key']);
        self::assertSame(['admin.docs'], $items[0]['routeNames']);
    }

    public function testServiceIgnoresHiddenFrontendPageAttachments(): void
    {
        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('docs.hidden')
                ->setPath('/docs/hidden')
                ->hideFromNavigation('docs'),
            Page::create('admin.dashboard')
                ->setPath('/admin')
                ->setAdminNavigation('Dashboard', key: 'dashboard'),
        ]);

        $items = (new AdminNavigationService(new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN), $pages, includeConfiguredItems: false))->items();

        self::assertCount(1, $items);
        self::assertSame('dashboard', $items[0]['key']);
        self::assertSame(['admin.dashboard'], $items[0]['routeNames']);
    }

    public function testServiceHidesPagesCurrentUserCannotAccess(): void
    {
        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('admin.pages')
                ->setPath('/admin/pages')
                ->requirePermission(Permission::PAGES_VIEW)
                ->setAdminNavigation('Pages', parent: 'content', key: 'pages'),
            Page::create('admin.users')
                ->setPath('/admin/users')
                ->requirePermission(Permission::USERS_VIEW)
                ->setAdminNavigation('Users', parent: 'system', key: 'users'),
        ]);

        $user = new UserHelper(null, [Permission::PAGES_VIEW], [], true);
        $items = (new AdminNavigationService(
            new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN),
            $pages,
            includeConfiguredItems: false,
            userResolver: static fn(): UserHelper => $user,
        ))->items();

        self::assertNotNull($this->findNode($items, 'pages'));
        self::assertNull($this->findNode($items, 'users'));
    }

    public function testServiceKeepsDeniedParentWhenChildIsVisible(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->add(
            NavigationItem::create('content')
                ->setLabel('Content')
                ->setScope('admin')
                ->addRouteName('admin.articles')
        );

        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('admin.articles')
                ->setPath('/admin/articles')
                ->requirePermission(Permission::ARTICLES_VIEW),
            Page::create('admin.pages')
                ->setPath('/admin/pages')
                ->requirePermission(Permission::PAGES_VIEW)
                ->setAdminNavigation('Pages', parent: 'content', key: 'pages'),
        ]);

        $user = new UserHelper(null, [Permission::PAGES_VIEW], [], true);
        $items = (new AdminNavigationService(
            $navigation,
            $pages,
            includeConfiguredItems: false,
            userResolver: static fn(): UserHelper => $user,
        ))->items();

        $content = $this->findNode($items, 'content');

        self::assertNotNull($content);
        self::assertSame('pages', $content['children'][0]['key']);
        self::assertSame(['admin.pages'], $content['routeNames']);
    }

    public function testAccentForPathInheritsParentNavigationAccent(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->add(
            NavigationItem::create('content')
                ->setLabel('Content')
                ->setScope('admin')
        );

        $pages = new PageRegistry();
        $pages->addMany([
            Page::create('admin.forms')
                ->setPath('/admin/forms')
                ->setAdminNavigation('Formulare', parent: 'content', key: 'forms'),
            Page::create('admin.forms.create')
                ->setPath('/admin/forms/create')
                ->hideFromAdminNavigation('forms'),
            Page::create('admin.forms.edit')
                ->setPath('/admin/forms/{formId}/edit')
                ->hideFromAdminNavigation('forms'),
        ]);

        $service = new AdminNavigationService(
            $navigation,
            $pages,
            includeConfiguredItems: false,
            accentColors: [
                'content' => '#3f7fbd',
                'forms' => '#ef4444',
                'settings' => '#8b5cf6',
            ],
        );

        self::assertSame('#3f7fbd', $service->accentForPath('/admin/forms'));
        self::assertSame('#3f7fbd', $service->accentForPath('/admin/forms/create'));
        self::assertSame('#3f7fbd', $service->accentForPath('/admin/forms/42/edit'));

        $forms = $this->findNode($service->items(), 'forms');
        self::assertNotNull($forms);
        self::assertSame('#3f7fbd', $forms['accent']);
    }

    public function testAccentForPathAllowsNavigationMetaOverride(): void
    {
        $navigation = new ScopedNavigationApi(new NavigationApi(new NavigationRepository(), false), Scope::ADMIN);
        $navigation->addMany([
            NavigationItem::create('plugins')
                ->setLabel('Plugins')
                ->setScope('admin')
                ->setMeta(['accent' => '#22c55e']),
            NavigationItem::create('plugins.forms')
                ->setLabel('Forms')
                ->setParent('plugins')
                ->setScope('admin'),
        ]);

        $pages = new PageRegistry();
        $pages->add(
            Page::create('admin.plugins.forms')
                ->setPath('/admin/plugins/forms')
                ->setAdminNavigation('Forms', parent: 'plugins.forms', key: 'plugins.forms.home')
        );

        $service = new AdminNavigationService(
            $navigation,
            $pages,
            includeConfiguredItems: false,
            accentColors: [
                'settings' => '#8b5cf6',
            ],
        );

        self::assertSame('#22c55e', $service->accentForPath('/admin/plugins/forms'));

        $home = $this->findNode($service->items(), 'plugins.forms.home');
        self::assertNotNull($home);
        self::assertSame('#22c55e', $home['accent']);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $key): ?array
    {
        foreach ($nodes as $node) {
            if (($node['key'] ?? null) === $key) {
                return $node;
            }

            $match = $this->findNode(is_array($node['children'] ?? null) ? $node['children'] : [], $key);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }
}
