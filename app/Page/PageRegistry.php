<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Controllers\Page\PageController;
use function NixPHP\route;

final class PageRegistry
{
    /** @var array<string, Page> */
    private array $pages = [];

    public function add(Page $page, bool $replace = false): self
    {
        if (isset($this->pages[$page->id()]) && !$replace) {
            throw new \LogicException(sprintf('Page "%s" is already registered.', $page->id()));
        }

        $this->pages[$page->id()] = $page;
        return $this;
    }

    /** @param iterable<Page> $pages */
    public function addMany(iterable $pages, bool $replace = false): self
    {
        foreach ($pages as $page) {
            $this->add($page, $replace);
        }

        return $this;
    }

    public function get(string $id): ?Page
    {
        return $this->pages[$id] ?? null;
    }

    public function require(string $id): Page
    {
        return $this->get($id) ?? throw new \RuntimeException(sprintf('Page "%s" is not registered.', $id));
    }

    public function findByPath(string $path, string $method = 'GET'): ?Page
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedMethod = strtoupper($method);

        foreach ($this->pages as $page) {
            if ($page->method() === $normalizedMethod && $page->path() === $normalizedPath) {
                return $page;
            }

            if ($page->path() === $normalizedPath && $page->dataHandler($normalizedMethod) instanceof PageDataHandler) {
                return $page;
            }
        }

        return null;
    }

    /** @return array<string, Page> */
    public function all(): array
    {
        return $this->pages;
    }

    public function registerRoutes(): void
    {
        foreach ($this->pages as $page) {
            route()->add($page->method(), $page->path(), [PageController::class, 'show'], $page->id());

            foreach ($page->dataHandlers() as $dataHandler) {
                route()->add(
                    $dataHandler->method(),
                    $page->path(),
                    [PageController::class, 'handleData'],
                    $this->dataRouteName($page, $dataHandler)
                );
            }
        }
    }

    public function dataRouteName(Page $page, PageDataHandler $dataHandler): string
    {
        return $page->id() . '.' . strtolower($dataHandler->method());
    }

    public function findByDataRouteName(string $routeName): ?Page
    {
        foreach ($this->pages as $page) {
            foreach ($page->dataHandlers() as $dataHandler) {
                if ($this->dataRouteName($page, $dataHandler) === $routeName) {
                    return $page;
                }
            }
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
