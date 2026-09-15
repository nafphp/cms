<?php

declare(strict_types=1);

namespace Naf\CMS\Models;

use Naf\CMS\Repositories\RoleRepository;
use Naf\ORM\Model\AbstractModel;

use function Naf\ORM\repo;

class Permission extends AbstractModel
{
    protected ?string $name      = null;
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
