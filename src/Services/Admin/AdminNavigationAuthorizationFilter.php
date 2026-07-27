<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Admin;

use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Page\PageRegistry;
use NixPHP\CMS\Support\UserHelper;
use NixPHP\CMS\Services\Admin\AdminNavigationItemCollector;
use NixPHP\CMS\Services\Admin\AdminNavigationTreeBuilder;
use function NixPHP\CMS\cms;
use function NixPHP\CMS\user;

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

        if ($page->requiresAuthentication() && ($user === null || !$user->isLoggedIn())) {
            return false;
        }

        foreach ($page->requiredRoles() as $role) {
            if ($user?->hasRole($role) !== true) {
                return false;
            }
        }

        $permissions = $page->requiredPermissions();
        if ($permissions === []) {
            return true;
        }

        if ($page->requiresAnyPermission()) {
            foreach ($permissions as $permission) {
                if ($user?->can($permission) === true) {
                    return true;
                }
            }

            return false;
        }

        foreach ($permissions as $permission) {
            if ($user?->can($permission) !== true) {
                return false;
            }
        }

        return true;
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
