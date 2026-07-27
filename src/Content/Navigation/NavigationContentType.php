<?php

declare(strict_types=1);

namespace src\Content\Navigation;

use src\Api\NavigationApi;
use src\Content\ContentTypeInterface;
use src\Models\SiteNavigation;
use src\Repositories\NavigationRepository;

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
