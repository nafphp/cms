<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use InvalidArgumentException;
use LogicException;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Enums\Role;
use Psr\Http\Message\ResponseInterface;

final class Page
{
    private string $method               = 'GET';
    private ?string $path                = null;
    private ?string $title               = null;
    private ?string $layout              = null;
    private ?Layout $pageLayout          = null;
    private bool $requiresAuthentication = false;
    /** @var array<int, Role> */
    private array $requiredRoles = [];
    /** @var array<int, Permission> */
    private array $requiredPermissions  = [];
    private bool $requiresAnyPermission = false;
    private bool $storedOverridable     = false;
    /** @var callable|null */
    private $content = null;
    /** @var callable|array{0: class-string, 1: string}|null */
    private mixed $handler = null;
    /** @var array<string, PageDataHandler> */
    private array $dataHandlers = [];
    private ?string $language   = null;
    private ?string $themeId    = null;
    /** @var array<string, string|bool> */
    private array $htmlAttributes = [];
    /** @var array<string, string|bool> */
    private array $bodyAttributes = [];
    /** @var array<string, string> */
    private array $bodyClasses = [];
    /** @var array<int, array{href: string, attributes: array<string, string|bool>}> */
    private array $stylesheets = [];
    /** @var array<int, array{src: string, attributes: array<string, string|bool>}> */
    private array $scripts = [];
    private PageMeta $meta;

    private function __construct(private readonly string $id)
    {
        $this->meta = new PageMeta();
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Let an editor's stored page take over this path.
     *
     * For code pages that only exist so the path is not empty, such as the
     * built-in home page. Without this a stored page on the same path is
     * silently unreachable, because the code route is registered first.
     */
    public function allowStoredOverride(bool $allow = true): self
    {
        $this->storedOverridable = $allow;

        return $this;
    }

    public function isStoredOverridable(): bool
    {
        return $this->storedOverridable;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper(trim($method));

        return $this;
    }

    public function path(): string
    {
        if ($this->path === null || $this->path === '') {
            throw new LogicException(sprintf('Page "%s" has no path.', $this->id));
        }

        return $this->path;
    }

    public function setPath(string $path): self
    {
        $path       = '/' . ltrim(trim($path), '/');
        $this->path = $path === '/' ? '/' : rtrim($path, '/');

        return $this;
    }

    public function title(): string
    {
        return $this->title ?? $this->id;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function layout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string|Template|Layout|null $layout): self
    {
        if ($layout instanceof Template) {
            $this->pageLayout = new Layout($layout);

            return $this;
        }

        if ($layout instanceof Layout) {
            $this->pageLayout = $layout;

            return $this;
        }

        if ($layout === null || trim($layout) === '') {
            $this->layout = null;

            return $this;
        }

        $layout       = trim($layout);
        $this->layout = str_contains($layout, '.') || str_contains($layout, '/')
            ? $layout
            : 'layouts.' . $layout;

        return $this;
    }

    public function requiresAuthentication(): bool
    {
        return $this->requiresAuthentication;
    }

    public function requireAuthentication(bool $required = true): self
    {
        $this->requiresAuthentication = $required;

        return $this;
    }

    /** @return array<int, Role> */
    public function requiredRoles(): array
    {
        return $this->requiredRoles;
    }

    public function requireRole(Role $role): self
    {
        if (!in_array($role, $this->requiredRoles, true)) {
            $this->requiredRoles[] = $role;
        }

        return $this->requireAuthentication();
    }

    public function requireAuthorization(Role $role): self
    {
        return $this->requireRole($role);
    }

    /** @return array<int, Permission> */
    public function requiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    public function requirePermission(Permission $permission): self
    {
        if (!in_array($permission, $this->requiredPermissions, true)) {
            $this->requiredPermissions[] = $permission;
        }

        $this->requiresAnyPermission = false;

        return $this->requireAuthentication();
    }

    public function requireAnyPermission(Permission ...$permissions): self
    {
        foreach ($permissions as $permission) {
            if (!in_array($permission, $this->requiredPermissions, true)) {
                $this->requiredPermissions[] = $permission;
            }
        }

        $this->requiresAnyPermission = true;

        return $this->requireAuthentication();
    }

    public function requiresAnyPermission(): bool
    {
        return $this->requiresAnyPermission;
    }

    public function setContent(callable|string|ResponseInterface $content): self
    {
        $this->content = is_callable($content) ? $content : static fn(): string|ResponseInterface => $content;

        return $this;
    }

    public function content(): callable
    {
        if ($this->content === null) {
            throw new LogicException(sprintf('Page "%s" has no content.', $this->id));
        }

        return $this->content;
    }

    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function setHandler(callable|array|string $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @return callable|array{0: class-string, 1: string}|class-string|null
     */
    public function handler(): callable|array|string|null
    {
        return $this->handler;
    }

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function setDataHandler(callable|array|string $handler, string $method = 'POST', ?Permission $permission = null): self
    {
        $dataHandler                                = new PageDataHandler($handler, $method, $permission);
        $this->dataHandlers[$dataHandler->method()] = $dataHandler;

        return $this;
    }

    public function dataHandler(string $method): ?PageDataHandler
    {
        return $this->dataHandlers[strtoupper(trim($method))] ?? null;
    }

    /** @return array<string, PageDataHandler> */
    public function dataHandlers(): array
    {
        return $this->dataHandlers;
    }

    public function pageLayout(): ?Layout
    {
        return $this->pageLayout;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        if ($language === null || trim($language) === '') {
            $this->language = null;

            return $this;
        }

        $this->language = strtolower(trim($language));

        return $this;
    }

    public function themeId(): ?string
    {
        return $this->themeId;
    }

    public function setTheme(?string $themeId): self
    {
        $themeId       = $themeId === null ? null : trim($themeId);
        $this->themeId = $themeId === '' ? null : $themeId;

        return $this;
    }

    public function setHtmlAttribute(string $name, string|bool|null $value): self
    {
        return $this->setAttribute($this->htmlAttributes, $name, $value);
    }

    /**
     * @return array<string, string|bool>
     */
    public function htmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    public function setBodyAttribute(string $name, string|bool|null $value): self
    {
        return $this->setAttribute($this->bodyAttributes, $name, $value);
    }

    /**
     * @return array<string, string|bool>
     */
    public function bodyAttributes(): array
    {
        return $this->bodyAttributes;
    }

    public function addBodyClass(string $class): self
    {
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $name) {
            if ($name !== '') {
                $this->bodyClasses[$name] = $name;
            }
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function bodyClasses(): array
    {
        return array_values($this->bodyClasses);
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addStylesheet(string $href, array $attributes = []): self
    {
        $href = trim($href);
        if ($href === '') {
            throw new InvalidArgumentException('Stylesheet href cannot be empty.');
        }

        $this->stylesheets[] = [
            'href'       => $href,
            'attributes' => $this->cleanAttributes($attributes),
        ];

        return $this;
    }

    /**
     * @return array<int, array{href: string, attributes: array<string, string|bool>}>
     */
    public function stylesheets(): array
    {
        return $this->stylesheets;
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addScript(string $src, array $attributes = []): self
    {
        $src = trim($src);
        if ($src === '') {
            throw new InvalidArgumentException('Script src cannot be empty.');
        }

        $this->scripts[] = [
            'src'        => $src,
            'attributes' => $this->cleanAttributes($attributes),
        ];

        return $this;
    }

    /**
     * @return array<int, array{src: string, attributes: array<string, string|bool>}>
     */
    public function scripts(): array
    {
        return $this->scripts;
    }

    public function meta(): PageMeta
    {
        return $this->meta;
    }

    /**
     * @param PageMeta|array<string, mixed> $meta
     */
    public function setMeta(PageMeta|array $meta): self
    {
        $this->meta = is_array($meta) ? PageMeta::fromArray($meta) : $meta;

        return $this;
    }

    public function setNavigation(
        ?string $label,
        ?string $parent = null,
        ?string $icon = null,
        int $order = 100,
        ?string $key = null,
        ?string $href = null,
        bool $visible = true,
        string $scope = 'frontend',
    ): self {
        $this->meta->navigation->label   = $label;
        $this->meta->navigation->parent  = $parent;
        $this->meta->navigation->icon    = $icon;
        $this->meta->navigation->order   = $order;
        $this->meta->navigation->key     = $key;
        $this->meta->navigation->href    = $href;
        $this->meta->navigation->visible = $visible;
        $this->meta->navigation->scope   = $scope;

        return $this;
    }

    public function setAdminNavigation(
        ?string $label,
        ?string $parent = null,
        ?string $icon = null,
        int $order = 100,
        ?string $key = null,
        ?string $href = null,
        bool $visible = true,
    ): self {
        return $this->setNavigation($label, $parent, $icon, $order, $key, $href, $visible, 'admin');
    }

    public function hideFromNavigation(?string $parent = null, string $scope = 'frontend'): self
    {
        $this->meta->navigation->visible = false;
        if ($parent !== null) {
            $this->meta->navigation->parent = $parent;
        }
        $this->meta->navigation->scope = $scope;

        return $this;
    }

    public function hideFromAdminNavigation(?string $parent = null): self
    {
        return $this->hideFromNavigation($parent, 'admin');
    }

    /**
     * @param array<string, string|bool> $target
     */
    private function setAttribute(array &$target, string $name, string|bool|null $value): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Attribute name cannot be empty.');
        }

        if ($value === null || $value === false) {
            unset($target[$name]);

            return $this;
        }

        $target[$name] = is_string($value) ? trim($value) : $value;

        return $this;
    }

    /**
     * @param array<string, string|bool> $attributes
     * @return array<string, string|bool>
     */
    private function cleanAttributes(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || $value === false) {
                continue;
            }

            $clean[$name] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }
}
