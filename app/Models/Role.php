<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixPHP\ORM\Model\AbstractModel;

final class Role extends AbstractModel
{
    protected ?string $name = null;
    protected ?string $createdAt = null;

    public array $pivotTables = [
        Permission::class => 'role_permissions',
    ];

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
