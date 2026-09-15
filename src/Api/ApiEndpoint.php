<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use LogicException;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Enums\Role;

final class ApiEndpoint
{
    private string $method               = 'GET';
    private ?string $path                = null;
    private bool $requiresAuthentication = false;
    /** @var array<int, Role> */
    private array $requiredRoles = [];
    /** @var array<int, Permission> */
    private array $requiredPermissions  = [];
    private bool $requiresAnyPermission = false;
    /** @var callable|array{0: class-string, 1: string}|class-string|null */
    private mixed $handler = null;

    private function __construct(private readonly string $id)
    {
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public static function get(string $id, string $path): self
    {
        return self::create($id)->setMethod('GET')->setPath($path);
    }

    public static function post(string $id, string $path): self
    {
        return self::create($id)->setMethod('POST')->setPath($path);
    }

    public static function put(string $id, string $path): self
    {
        return self::create($id)->setMethod('PUT')->setPath($path);
    }

    public static function delete(string $id, string $path): self
    {
        return self::create($id)->setMethod('DELETE')->setPath($path);
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
            throw new LogicException(sprintf('API endpoint "%s" has no path.', $this->id));
        }

        return $this->path;
    }

    public function setPath(string $path): self
    {
        $path       = '/' . ltrim(trim($path), '/');
        $this->path = $path === '/' ? '/' : rtrim($path, '/');

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

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function setHandler(callable|array|string $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function handler(): callable|array|string
    {
        if ($this->handler === null) {
            throw new LogicException(sprintf('API endpoint "%s" has no handler.', $this->id));
        }

        return $this->handler;
    }
}
