<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use LogicException;
use Naf\CMS\Controllers\Page\PageController;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Repositories\PageLayoutRepository;
use Naf\CMS\Repositories\PageRepository;
use Naf\CMS\Support\AuditEntry;
use RuntimeException;

use function Naf\CMS\cms;
use function Naf\CMS\user;
use function Naf\log;
use function Naf\route;

final class PageRegistry
{
    /** @var array<string, Page> */
    private array $pages = [];
    /** @var array<string, string> */
    private array $routeAliases         = [];
    private ?PageRepository $repository = null;
    private bool $storedPagesLoaded     = false;
    private bool $storedRoutesResolved  = false;
    /** @var array<int, array{id: string, path: string, published: bool}>|null */
    private ?array $storedRouteDescriptors = null;

    public function add(Page $page, bool $replace = false): self
    {
        if (isset($this->pages[$page->id()]) && !$replace) {
            throw new LogicException(sprintf('Page "%s" is already registered.', $page->id()));
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
        return $this->get($id) ?? throw new RuntimeException(sprintf('Page "%s" is not registered.', $id));
    }

    public function findByPath(string $path, string $method = 'GET'): ?Page
    {
        // Only the shadowing bookkeeping, not the stored pages themselves: a
        // stored page is built from the database further down, at the moment it
        // is actually needed.
        $this->resolveStoredRoutes();

        $normalizedPath   = $this->normalizePath($path);
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
            fallbackLanguage: cms()->settings()->fallbackLanguage(),
        );
    }

    /**
     * Every registered page, stored pages included.
     *
     * Callers that enumerate pages (navigation targets, docs, dashboards) need
     * the stored ones too, so this materializes them. Rendering a single page
     * does not go through here.
     *
     * @return array<string, Page>
     */
    public function all(): array
    {
        $this->loadStoredPages();

        return $this->pages;
    }

    public function registerRoutes(): void
    {
        // Deliberately does not build stored pages. Route registration happens
        // while plugin route files are loaded, which is before this package's
        // bootstrap has registered block types and content types. Constructing
        // page content that early silently drops every block, so routing works
        // from lightweight descriptors and the page itself is built per request.
        $this->resolveStoredRoutes();

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
                    $this->dataRouteName($page, $dataHandler),
                );
            }
        }

        foreach ($this->storedRouteDescriptors() as $descriptor) {
            if (isset($this->pages[$descriptor['id']])) {
                continue;
            }

            // A code page still owns this path, so it was not replaced above.
            // Registering anyway would leave a named route that resolves to
            // someone else's page, and the editor would never learn why their
            // page stays invisible.
            $blocking = $this->findCodePageByPath($descriptor['path']);
            if ($blocking instanceof Page) {
                // Only worth reporting for a page that is meant to be live. A
                // draft losing the path to a code page is the expected state,
                // not a problem the editor has to act on.
                if ($descriptor['published']) {
                    log()->warning(sprintf(
                        'Stored page "%s" is unreachable: path "%s" is already served by the registered page "%s".',
                        $descriptor['id'],
                        $descriptor['path'],
                        $blocking->id(),
                    ));
                }

                continue;
            }

            route()->add('GET', $descriptor['path'], [PageController::class, 'show'], $descriptor['id']);

            foreach ($this->routeAliasesForId($descriptor['id']) as $routeName) {
                route()->add('GET', $descriptor['path'], [PageController::class, 'show'], $routeName);
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

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository()->find($id);
    }

    public function storedPathOwnerId(string $path, string $language): ?int
    {
        return $this->repository()->pathLanguageOwnerId($path, $language);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        $old = null;
        $id  = (int) ($payload['id'] ?? 0);
        if ($id > 0) {
            $old = $this->repository()->find($id);
        }

        $savedId = $this->repository()->save($payload);
        $new     = $this->repository()->find($savedId);

        AuditEntry::create(
            $old === null ? 'page.created' : 'page.updated',
            'page',
            (string) $savedId,
            $this->pageAuditSnapshot($old),
            $this->pageAuditSnapshot($new),
            [
                'path'     => (string) ($new['path'] ?? $payload['path'] ?? ''),
                'language' => (string) ($new['language'] ?? $payload['language'] ?? ''),
            ],
        )->record();

        return $savedId;
    }

    public function publish(int $id): bool
    {
        $old       = $this->repository()->find($id);
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
        $old     = $this->repository()->find($id);
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
            'id'          => (int) ($page['id'] ?? 0),
            'identifier'  => (string) ($page['identifier'] ?? ''),
            'title'       => (string) ($page['title'] ?? ''),
            'path'        => (string) ($page['path'] ?? ''),
            'language'    => (string) ($page['language'] ?? ''),
            'status'      => (string) ($page['status'] ?? ''),
            'template_id' => (string) ($page['template_id'] ?? ''),
            'layout'      => (string) ($page['layout'] ?? ''),
            'blocks'      => $page['blocks'] ?? [],
            'meta'        => $page['meta'] ?? [],
        ];
    }

    /**
     * Work out which code pages a stored page takes over, without touching page
     * content. Cheap enough to run while routes are being registered.
     */
    private function resolveStoredRoutes(): void
    {
        if ($this->storedRoutesResolved) {
            return;
        }

        $this->storedRoutesResolved = true;

        foreach ($this->storedRouteDescriptors() as $descriptor) {
            $collision = $this->findCodePageByPath($descriptor['path']);
            if (!$collision instanceof Page || str_starts_with($collision->id(), 'db.page.')) {
                continue;
            }

            if (!$this->storedRouteMayReplaceCodePage($descriptor, $collision)) {
                continue;
            }

            $this->routeAliases[$collision->id()] = $descriptor['id'];
            unset($this->pages[$collision->id()]);
        }
    }

    /**
     * @return array<int, array{id: string, path: string, published: bool}>
     */
    private function storedRouteDescriptors(): array
    {
        return $this->storedRouteDescriptors ??= $this->repository()->routeDescriptors(
            array_column($this->pageTemplates(), 'id'),
        );
    }

    /**
     * Materialize stored pages as full Page objects.
     *
     * Never call this while routes are being registered; see registerRoutes().
     */
    private function loadStoredPages(): void
    {
        if ($this->storedPagesLoaded) {
            return;
        }

        $this->storedPagesLoaded = true;
        $this->resolveStoredRoutes();
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
        return $this->storedRouteMayReplaceCodePage(
            [
                'id'        => $storedPage->id(),
                'path'      => $storedPage->path(),
                'published' => !in_array(Permission::PAGES_VIEW, $storedPage->requiredPermissions(), true),
            ],
            $codePage,
        );
    }

    /**
     * A stored page takes over a code page's path when that code page opted in
     * and the stored page is actually published.
     *
     * The opt-in replaces a hard-coded "only / may replace only the page called
     * home" rule, which silently dropped every other stored page that landed on
     * a registered path.
     *
     * @param array{id: string, path: string, published: bool} $descriptor
     */
    private function storedRouteMayReplaceCodePage(array $descriptor, Page $codePage): bool
    {
        return $codePage->isStoredOverridable()
            && $codePage->method() === 'GET'
            && $this->normalizePath($codePage->path()) === $this->normalizePath($descriptor['path'])
            && $descriptor['published'];
    }

    /** @return array<int, string> */
    private function routeAliasesFor(Page $page): array
    {
        return $this->routeAliasesForId($page->id());
    }

    /** @return array<int, string> */
    private function routeAliasesForId(string $pageId): array
    {
        $aliases = [];
        foreach ($this->routeAliases as $routeName => $id) {
            if ($id === $pageId) {
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
        return cms()->settings()->currentLanguage();
    }

    private function findCodePageByPath(string $path, string $method = 'GET'): ?Page
    {
        $normalizedPath   = $this->normalizePath($path);
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
