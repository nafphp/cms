<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Controllers\Page\PageController;
use NixCMS\Enums\Permission;
use NixCMS\Repositories\PageRepository;
use function NixPHP\I18n\lang;
use function NixPHP\route;

final class PageRegistry
{
    /** @var array<string, Page> */
    private array $pages = [];
    /** @var array<string, string> */
    private array $routeAliases = [];
    private ?PageRepository $repository = null;
    private bool $storedPagesLoaded = false;

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
        $this->loadStoredPages();

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

        if ($normalizedMethod !== 'GET') {
            return null;
        }

        $templates = $this->pageTemplates();
        if ($templates === []) {
            return null;
        }

        return $this->repository()->pageByPath(
            $normalizedPath,
            $templates,
            $this->activeLanguage(),
            publishedOnly: \user()?->can(Permission::PAGES_VIEW) !== true,
        );
    }

    /** @return array<string, Page> */
    public function all(): array
    {
        return $this->pages;
    }

    public function registerRoutes(): void
    {
        $this->loadStoredPages();

        foreach ($this->pages as $page) {
            route()->add($page->method(), $page->path(), [PageController::class, 'show'], $page->id());

            foreach ($this->routeAliasesFor($page) as $routeName) {
                route()->add($page->method(), $page->path(), [PageController::class, 'show'], $routeName);
            }

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

    /**
     * @param array<int, array<string, mixed>> $templates
     * @return array<int, array<string, mixed>>
     */
    public function summaries(array $templates = []): array
    {
        return $this->repository()->summaries($templates);
    }

    public function find(int $id): ?array
    {
        return $this->repository()->find($id);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        return $this->repository()->save($payload);
    }

    public function publish(int $id): bool
    {
        return $this->repository()->publish($id);
    }

    public function delete(int $id): bool
    {
        return $this->repository()->delete($id);
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

    private function loadStoredPages(): void
    {
        if ($this->storedPagesLoaded) {
            return;
        }

        $this->storedPagesLoaded = true;
        $templates = $this->pageTemplates();

        foreach ($this->repository()->pagesForRouting($templates) as $page) {
            $collision = $this->findCodePageByPath($page->path(), $page->method());
            if ($collision instanceof Page && !str_starts_with($collision->id(), 'db.page.')) {
                if (!$this->storedPageMayReplaceCodePage($page, $collision)) {
                    continue;
                }

                $this->routeAliases[$collision->id()] = $page->id();
                unset($this->pages[$collision->id()]);
            }

            $this->add($page, true);
        }
    }

    private function storedPageMayReplaceCodePage(Page $storedPage, Page $codePage): bool
    {
        if ($storedPage->path() !== '/' || $storedPage->method() !== 'GET') {
            return false;
        }

        if ($codePage->id() !== 'home') {
            return false;
        }

        return !in_array(Permission::PAGES_VIEW, $storedPage->requiredPermissions(), true);
    }

    /** @return array<int, string> */
    private function routeAliasesFor(Page $page): array
    {
        $aliases = [];
        foreach ($this->routeAliases as $routeName => $pageId) {
            if ($pageId === $page->id()) {
                $aliases[] = $routeName;
            }
        }

        return $aliases;
    }

    private function repository(): PageRepository
    {
        return $this->repository ??= new PageRepository();
    }

    private function activeLanguage(): string
    {
        $language = strtolower(trim((string) lang()));
        if ($language !== '') {
            return $language;
        }

        return \cms()->settings()->language();
    }

    private function findCodePageByPath(string $path, string $method = 'GET'): ?Page
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageTemplates(): array
    {
        $templateFile = BASE_PATH . '/app/Resources/pages/page_templates.php';
        if (!is_file($templateFile)) {
            return [];
        }

        $templates = require $templateFile;
        return is_array($templates) ? $templates : [];
    }
}
