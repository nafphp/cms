<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixPHP\ORM\Model\AbstractModel;

final class Category extends AbstractModel
{
    protected ?string $name = null;
    protected ?string $slug = null;
    protected ?string $createdAt = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }
}
