<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use BadMethodCallException;
use Naf\CMS\Api\ScopedNavigationApi;
use Naf\CMS\Contracts\ScopeAwareInterface;
use Naf\CMS\Enums\Scope;

/** @method \Naf\CMS\Page\PageRegistry pages() */
final class ScopedCms
{
    /** @var array<string, object> */
    private array $scopedApis = [];

    public function __construct(
        private readonly Cms $cms,
        private readonly Scope $scope,
    ) {
    }

    public function scope(): Scope
    {
        return $this->scope;
    }

    public function navigation(): ScopedNavigationApi
    {
        /** @var ScopedNavigationApi $navigation */
        $navigation = $this->__call('navigation', []);

        return $navigation;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (!method_exists($this->cms, $method)) {
            throw new BadMethodCallException(sprintf('Undefined scoped CMS method "%s".', $method));
        }

        $result = $this->cms->{$method}(...$arguments);
        if (!$result instanceof ScopeAwareInterface) {
            return $result;
        }

        $cacheKey = $this->cacheKey($method, $arguments);

        return $this->scopedApis[$cacheKey] ??= $result->forScope($this->scope);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function cacheKey(string $method, array $arguments): string
    {
        if ($arguments === []) {
            return $method;
        }

        return $method . ':' . md5(serialize($arguments));
    }
}
