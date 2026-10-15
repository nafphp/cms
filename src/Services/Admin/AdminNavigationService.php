<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Admin;

use Naf\CMS\Api\ScopedNavigationApi;
use Naf\CMS\Page\PageRegistry;

final class AdminNavigationService
{
    /**
     * @param array<string, string>|null $accentColors
     */
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
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->authorizationFilter()->filter(
            $this->colorInheritance()->apply(
                $this->treeBuilder()->build($this->collector()->collect()),
            ),
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
