<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixCMS\Stage\Attributes\StageField;
use NixPHP\ORM\Model\AbstractModel;

class SiteNavigation extends AbstractModel
{
    #[StageField(writable: false)]
    protected ?string $identifier = null;
    #[StageField]
    protected ?string $title = null;
    #[StageField]
    protected ?string $scope = 'frontend';
    #[StageField]
    protected ?string $language = null;
    #[StageField]
    protected ?string $status = 'draft';
    #[StageField(name: 'items', json: true)]
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
