<?php

declare(strict_types=1);

namespace Naf\CMS\Models;

use Naf\ORM\Model\AbstractModel;

class Form extends AbstractModel
{
    protected ?string $identifier = null;
    protected ?string $title = null;
    protected ?string $status = 'draft';
    protected ?string $fields_json = null;
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
