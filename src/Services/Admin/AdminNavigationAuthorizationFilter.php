<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Admin;

use Naf\CMS\Page\Page;
use Naf\CMS\Page\PageRegistry;
use Naf\CMS\Support\UserHelper;
use Naf\CMS\Services\Admin\AdminNavigationItemCollector;
use Naf\CMS\Services\Admin\AdminNavigationTreeBuilder;
use function Naf\CMS\cms;
use function Naf\CMS\user;

final class AdminNavigationAuthorizationFilter
{
    public function __construct(
        private ?PageRegistry $pages = null,
        private mixed $userResolver = null,
        private ?AdminNavigationTreeBuilder $treeBuilder = null,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $items): array
    {
        $visible = [];

        foreach ($items as $item) {
            $children = $this->filter(is_array($item['children'] ?? null) ? $item['children'] : []);
            $item['children'] = $children;

            if ($children !== [] || $this->isItemAuthorized($item)) {
                $item['routeNames'] = AdminNavigationItemCollector::routeNames([
                    ...$this->authorizedRouteNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []),
                    ...$this->treeBuilder()->descendantRouteNames($children),
                ]);
                $visible[] = $item;
            }
        }

        return $visible;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isItemAuthorized(array $item): bool
    {
        $routeNames = AdminNavigationItemCollector::routeNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []);
        if ($routeNames === []) {
            return true;
        }

        return $this->authorizedRouteNames($routeNames) !== [];
    }

    /**
     * @param array<int, mixed> $routeNames
     * @return array<int, string>
     */
    private function authorizedRouteNames(array $routeNames): array
    {
        $authorized = [];
        $routeNames = AdminNavigationItemCollector::routeNames($routeNames);
        foreach ($routeNames as $routeName) {
            $page = $this->pages()->get($routeName);
            if (!$page instanceof Page || $this->isPageAuthorized($page)) {
                $authorized[] = $routeName;
            }
        }

        return AdminNavigationItemCollector::routeNames($authorized);
    }

    private function isPageAuthorized(Page $page): bool
    {
        $user = $this->currentUser();
        try {
            (new \Naf\CMS\Services\Security\ResourceAuthorization())->authorize(
                $user?->isLoggedIn() === true ? $user : null,
                $page->requiresAuthentication(), $page->requiredRoles(),
                $page->requiredPermissions(), $page->requiresAnyPermission(),
            );
            return true;
        } catch (\Naf\Auth\Exceptions\UnauthenticatedException|\Naf\Auth\Exceptions\ForbiddenException) {
            return false;
        }
    }

    private function currentUser(): ?UserHelper
    {
        if (is_callable($this->userResolver)) {
            $user = ($this->userResolver)();
            return $user instanceof UserHelper ? $user : null;
        }

        return user();
    }

    private function pages(): PageRegistry
    {
        return $this->pages ??= cms()->pages();
    }

    private function treeBuilder(): AdminNavigationTreeBuilder
    {
        return $this->treeBuilder ??= new AdminNavigationTreeBuilder();
    }
}
