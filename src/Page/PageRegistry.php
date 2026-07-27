<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

use NixPHP\CMS\Controllers\Page\PageController;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Repositories\PageRepository;
use NixPHP\CMS\Repositories\PageLayoutRepository;
use NixPHP\CMS\Support\AuditEntry;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Page\PageDataHandler;
use NixPHP\CMS\Page\PageTemplateCatalog;
use function NixPHP\I18n\lang;
use function NixPHP\route;
use function NixPHP\CMS\cms;
use function NixPHP\CMS\user;

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
            publishedOnly: user()?->can(Permission::PAGES_VIEW) !== true,
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
        $old = null;
        $id = (int) ($payload['id'] ?? 0);
        if ($id > 0) {
            $old = $this->repository()->find($id);
        }

        $savedId = $this->repository()->save($payload);
        $new = $this->repository()->find($savedId);

        AuditEntry::create(
            $old === null ? 'page.created' : 'page.updated',
            'page',
            (string) $savedId,
            $this->pageAuditSnapshot($old),
            $this->pageAuditSnapshot($new),
            [
                'path' => (string) ($new['path'] ?? $payload['path'] ?? ''),
                'language' => (string) ($new['language'] ?? $payload['language'] ?? ''),
            ],
        )->record();

        return $savedId;
    }

    public function publish(int $id): bool
    {
        $old = $this->repository()->find($id);
        $published = $this->repository()->publish($id);
        if ($published) {
            AuditEntry::create(
                'page.published',
                'page',
                (string) $id,
                $this->pageAuditSnapshot($old),
                $this->pageAuditSnapshot($this->repository()->find($id)),
            )->record();
        }

        return $published;
    }

    public function delete(int $id): bool
    {
        $old = $this->repository()->find($id);
        $deleted = $this->repository()->delete($id);
        if ($deleted) {
            AuditEntry::create(
                'page.deleted',
                'page',
                (string) $id,
                $this->pageAuditSnapshot($old),
            )->record();
        }

        return $deleted;
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

    /**
     * @param array<string, mixed>|null $page
     * @return array<string, mixed>|null
     */
    private function pageAuditSnapshot(?array $page): ?array
    {
        if ($page === null) {
            return null;
        }

        return [
            'id' => (int) ($page['id'] ?? 0),
            'identifier' => (string) ($page['identifier'] ?? ''),
            'title' => (string) ($page['title'] ?? ''),
            'path' => (string) ($page['path'] ?? ''),
            'language' => (string) ($page['language'] ?? ''),
            'status' => (string) ($page['status'] ?? ''),
            'template_id' => (string) ($page['template_id'] ?? ''),
            'layout' => (string) ($page['layout'] ?? ''),
            'blocks' => $page['blocks'] ?? [],
            'meta' => $page['meta'] ?? [],
        ];
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

        return cms()->settings()->language();
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
        return [
            ...(new PageTemplateCatalog())->codeDefinitions(),
            ...(new PageLayoutRepository())->templates(),
        ];
    }
}
