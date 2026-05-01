<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Enums\Permission;
use NixCMS\Enums\Role;
use Psr\Http\Message\ResponseInterface;

final class Page
{
    private string $method = 'GET';
    private ?string $path = null;
    private ?string $title = null;
    private ?string $layout = null;
    private bool $requiresAuthentication = false;
    /** @var array<int, Role> */
    private array $requiredRoles = [];
    /** @var array<int, Permission> */
    private array $requiredPermissions = [];
    private bool $requiresAnyPermission = false;
    /** @var callable|null */
    private $content = null;
    /** @var callable|array{0: class-string, 1: string}|null */
    private mixed $handler = null;
    /** @var array<string, PageDataHandler> */
    private array $dataHandlers = [];
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

    public function setMethod(string $method): self
    {
        $this->method = strtoupper(trim($method));
        return $this;
    }

    public function path(): string
    {
        if ($this->path === null || $this->path === '') {
            throw new \LogicException(sprintf('Page "%s" has no path.', $this->id));
        }

        return $this->path;
    }

    public function setPath(string $path): self
    {
        $path = '/' . ltrim(trim($path), '/');
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

    public function setLayout(?string $layout): self
    {
        if ($layout === null || trim($layout) === '') {
            $this->layout = null;
            return $this;
        }

        $layout = trim($layout);
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
            throw new \LogicException(sprintf('Page "%s" has no content.', $this->id));
        }

        return $this->content;
    }

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function setHandler(callable|array|string $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function handler(): callable|array|string|null
    {
        return $this->handler;
    }

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function setDataHandler(callable|array|string $handler, string $method = 'POST', ?Permission $permission = null): self
    {
        $dataHandler = new PageDataHandler($handler, $method, $permission);
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

    public function meta(): PageMeta
    {
        return $this->meta;
    }

    public function setMeta(PageMeta|array $meta): self
    {
        $this->meta = is_array($meta) ? PageMeta::fromArray($meta) : $meta;
        return $this;
    }
}
