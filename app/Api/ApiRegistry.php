<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Controllers\Api\ApiController;
use function NixPHP\route;

final class ApiRegistry
{
    /** @var array<string, ApiEndpoint> */
    private array $endpoints = [];

    public function add(ApiEndpoint $endpoint, bool $replace = false): self
    {
        if (isset($this->endpoints[$endpoint->id()]) && !$replace) {
            throw new \LogicException(sprintf('API endpoint "%s" is already registered.', $endpoint->id()));
        }

        $this->endpoints[$endpoint->id()] = $endpoint;
        return $this;
    }

    /** @param iterable<ApiEndpoint> $endpoints */
    public function addMany(iterable $endpoints, bool $replace = false): self
    {
        foreach ($endpoints as $endpoint) {
            $this->add($endpoint, $replace);
        }

        return $this;
    }

    public function get(string $id): ?ApiEndpoint
    {
        return $this->endpoints[$id] ?? null;
    }

    public function require(string $id): ApiEndpoint
    {
        return $this->get($id) ?? throw new \RuntimeException(sprintf('API endpoint "%s" is not registered.', $id));
    }

    public function findByPath(string $path, string $method): ?ApiEndpoint
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedMethod = strtoupper(trim($method));

        foreach ($this->endpoints as $endpoint) {
            if ($endpoint->method() === $normalizedMethod && $endpoint->path() === $normalizedPath) {
                return $endpoint;
            }
        }

        return null;
    }

    /** @return array<string, ApiEndpoint> */
    public function all(): array
    {
        return $this->endpoints;
    }

    public function registerRoutes(): void
    {
        foreach ($this->endpoints as $endpoint) {
            route()->add($endpoint->method(), $endpoint->path(), [ApiController::class, 'handle'], $endpoint->id());
        }
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
