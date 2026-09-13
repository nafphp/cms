<?php

declare(strict_types=1);

namespace Naf\CMS\Models;

use Naf\CMS\Enums\Role as RoleEnum;
use Naf\CMS\Enums\Permission as PermissionEnum;
use Naf\CMS\Repositories\RoleRepository;
use Naf\CMS\Repositories\UserRepository;
use Naf\ORM\Model\AbstractModel;
use Naf\CMS\Models\Role;
use function Naf\ORM\repo;

final class User extends AbstractModel
{
    protected ?string $provider = null;
    protected ?string $provider_id = null;
    protected ?string $firstname = null;
    protected ?string $lastname = null;
    protected ?string $email = null;
    protected ?string $username = null;
    protected ?string $password = null;
    protected ?string $createdAt = null;

    public array $pivotTables = [
        Role::class => 'user_role',
    ];

    /**
     * @return array<int, RoleEnum>
     */
    public function getRoles(): array
    {
        $rows = repo(RoleRepository::class)->findByPivot(self::class, (int) $this->id);

        return array_values(array_filter(array_map(
            static fn(Role $role): ?RoleEnum => RoleEnum::tryFrom((string) $role->getName()),
            $rows
        )));
    }

    /**
     * @return array<int, PermissionEnum>
     */
    public function getPermissions(): array
    {
        return repo(UserRepository::class)->getPermissions($this);
    }

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getProviderId(): ?string
    {
        return $this->provider_id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getDisplayName(): string
    {
        $first = trim((string) $this->firstname);
        $last = trim((string) $this->lastname);
        $combined = trim($first . ' ' . $last);
        return $combined !== '' ? $combined : (string) $this->username;
    }
}
