<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Navigation;

use Naf\CMS\Api\NavigationApi;
use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Models\SiteNavigation;
use Naf\CMS\Repositories\NavigationRepository;

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
