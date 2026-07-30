<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Navigation;

use NixPHP\CMS\Api\NavigationApi;
use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Models\SiteNavigation;
use NixPHP\CMS\Repositories\NavigationRepository;

final class NavigationContentType implements ContentTypeInterface
{
    private ?NavigationApi $api = null;

    public function id(): string
    {
        return 'navigation';
    }

    public function label(): string
    {
        return 'Navigation';
    }

    public function modelClass(): string
    {
        return SiteNavigation::class;
    }

    public function api(): NavigationApi
    {
        if ($this->api === null) {
            $repository = new NavigationRepository();
            $this->api = new NavigationApi($repository);
        }

        return $this->api;
    }
}
