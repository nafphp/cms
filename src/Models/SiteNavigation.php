<?php

declare(strict_types=1);

namespace src\Models;

use NixPHP\ORM\Model\AbstractModel;

class SiteNavigation extends AbstractModel
{
    protected ?string $identifier = null;
    protected ?string $title = null;
    protected ?string $scope = 'frontend';
    protected ?string $language = null;
    protected ?string $status = 'draft';
    protected ?string $items_json = null;
    protected ?string $created_at = null;
    protected ?string $updated_at = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getScope(): string
    {
        return $this->scope ?? 'frontend';
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getStatus(): string
    {
        return $this->status ?? 'draft';
    }

    public function getItemsJson(): ?string
    {
        return $this->items_json;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updated_at;
    }
}
