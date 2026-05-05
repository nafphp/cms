<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixCMS\Stage\Attributes\StageField;
use NixPHP\ORM\Model\AbstractModel;

class Form extends AbstractModel
{
    #[StageField(writable: false)]
    protected ?string $identifier = null;
    #[StageField]
    protected ?string $title = null;
    #[StageField]
    protected ?string $status = 'draft';
    #[StageField(name: 'fields', json: true)]
    protected ?string $fields_json = null;
    #[StageField(name: 'settings', json: true)]
    protected ?string $settings_json = null;
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

    public function getStatus(): string
    {
        return $this->status ?? 'draft';
    }

    public function getFieldsJson(): ?string
    {
        return $this->fields_json;
    }

    public function getSettingsJson(): ?string
    {
        return $this->settings_json;
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
