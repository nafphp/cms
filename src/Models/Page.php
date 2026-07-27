<?php

declare(strict_types=1);

namespace src\Models;

use NixPHP\ORM\Model\AbstractModel;

class Page extends AbstractModel
{
    protected ?string $identifier = null;
    protected ?string $path = null;
    protected ?string $title = null;
    protected ?string $language = null;
    protected ?string $status = 'draft';
    protected ?string $template_id = null;
    protected ?string $theme_id = null;
    protected ?string $blocks_json = null;
    protected ?string $meta_json = null;
    protected ?string $layout = 'layouts.page';
    protected ?string $created_at = null;
    protected ?string $updated_at = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getStatus(): string
    {
        return $this->status ?? 'draft';
    }

    public function getTemplateId(): ?string
    {
        return $this->template_id;
    }

    public function getThemeId(): ?string
    {
        return $this->theme_id;
    }

    public function getBlocksJson(): ?string
    {
        return $this->blocks_json;
    }

    public function getMetaJson(): ?string
    {
        return $this->meta_json;
    }

    public function getLayout(): string
    {
        return $this->layout ?? 'layouts.page';
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
