<?php

declare(strict_types=1);

namespace NixCMS\Content\Navigation;

use NixCMS\Api\NavigationApi;
use NixCMS\Content\ContentTypeInterface;
use NixCMS\Models\SiteNavigation;
use NixCMS\Repositories\NavigationRepository;

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
        return $this->api ??= new NavigationApi(new NavigationRepository());
    }
}
