<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Admin;

use NixPHP\CMS\Api\ScopedNavigationApi;
use NixPHP\CMS\Page\PageRegistry;
use NixPHP\CMS\Services\Admin\AdminNavigationAuthorizationFilter;
use NixPHP\CMS\Services\Admin\AdminNavigationColorInheritance;
use NixPHP\CMS\Services\Admin\AdminNavigationItemCollector;
use NixPHP\CMS\Services\Admin\AdminNavigationTreeBuilder;

final class AdminNavigationService
{
    public function __construct(
        private ?ScopedNavigationApi $navigation = null,
        private ?PageRegistry $pages = null,
        private bool $includeConfiguredItems = true,
        private mixed $userResolver = null,
        private ?array $accentColors = null,
        private ?AdminNavigationItemCollector $collector = null,
        private ?AdminNavigationTreeBuilder $treeBuilder = null,
        private ?AdminNavigationAuthorizationFilter $authorizationFilter = null,
        private ?AdminNavigationColorInheritance $colorInheritance = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->authorizationFilter()->filter(
            $this->colorInheritance()->apply(
                $this->treeBuilder()->build($this->collector()->collect())
            )
        );
    }

    public function accentForPath(string $path): string
    {
        return $this->colorInheritance()->accentForPath($path, $this->collector()->collect());
    }

    private function collector(): AdminNavigationItemCollector
    {
        return $this->collector ??= new AdminNavigationItemCollector(
            $this->navigation,
            $this->pages,
            $this->includeConfiguredItems,
        );
    }

    private function treeBuilder(): AdminNavigationTreeBuilder
    {
        return $this->treeBuilder ??= new AdminNavigationTreeBuilder();
    }

    private function authorizationFilter(): AdminNavigationAuthorizationFilter
    {
        return $this->authorizationFilter ??= new AdminNavigationAuthorizationFilter(
            $this->pages,
            $this->userResolver,
            $this->treeBuilder(),
        );
    }

    private function colorInheritance(): AdminNavigationColorInheritance
    {
        return $this->colorInheritance ??= new AdminNavigationColorInheritance(
            $this->pages,
            $this->accentColors,
        );
    }
}
