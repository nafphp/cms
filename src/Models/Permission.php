<?php

declare(strict_types=1);

namespace NixPHP\CMS\Models;

use NixPHP\CMS\Repositories\RoleRepository;
use NixPHP\ORM\Model\AbstractModel;
use NixPHP\CMS\Models\Role;
use function NixPHP\ORM\repo;

class Permission extends AbstractModel
{
    protected ?string $name = null;
    protected ?string $createdAt = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * @return array<int, Role>
     */
    public function getRoles(): array
    {
        return repo(RoleRepository::class)->findByPivot(self::class, (int) $this->id);
    }
}
