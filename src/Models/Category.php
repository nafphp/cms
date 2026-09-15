<?php

declare(strict_types=1);

namespace Naf\CMS\Models;

use Naf\ORM\Model\AbstractModel;

final class Category extends AbstractModel
{
    protected ?string $name      = null;
    protected ?string $slug      = null;
    protected ?string $createdAt = null;

    public function getTableName(bool $singular = false): string
    {
        return $singular ? 'category' : 'categories';
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }
}
